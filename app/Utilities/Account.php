<?php

namespace App\Utilities;

use App\Models\B;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
#use Illuminate\Support\Facades\Cache;
use App\Models\Issuer;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;

/**
 * This class loads account info from ledger and other sources.
 * Used for account summary route.
 */
class Account
{
  private string $address;
  private array $data;
  /**
   * If no endpoint failed this will be true, and it is safe to save to disk.
   * If true all data is complete.
   */
  private bool $is_completefetch = false;
  private bool $is_success = true;
  private bool $do_store = true;

  public function __construct(string $address)
  {
    //deleted acc: rPmtfaCLtZFLfQhsym7LPSUYsy1pJvo4QE
    //unactivated acc: rBJY6b6RruzE3HsYPbVLN3WHzViqwNS9Ws
    //bitstamp acc: rvYAfWj5gh67oV6fW32ZzP3Aw4Eubs59B
    
    $this->address = $address;

    try {
      $this->fetch();
    } catch (\Exception $e) {
      //XRPL connection failed
      $this->is_success = false;
    }

    if($this->is_success) {
      if($this->is_completefetch && $this->do_store) {
        //Save to disk
        $this->storeToDisk();
      }
    }
  }

  private function storeToDisk(): void
  {
    $internalpath = 'accounts/'.$this->address.'.json';

    if(Storage::disk('local')->exists($internalpath))
        Storage::disk('local')->delete($internalpath); //update

    if(! Storage::disk('local')->put($internalpath, \json_encode($this->data))) {
      $this->is_success = false;
    }
  }

  private function fetchFromDisk(): ?array
  {
    $internalpath = 'accounts/'.$this->address.'.json';
    if(!Storage::disk('local')->exists($internalpath))
      return null;
    return \json_decode(Storage::disk('local')->get($internalpath),true);
  }

  public function isComplete()
  {
    return $this->is_completefetch;
  }

  public function get(): ?array
  {
    if(!$this->is_success)
      return null;

    return $this->data;
  }

  private function fetch(): void
  {
    $storedData = $this->fetchFromDisk();
    if(\is_array($storedData)) {
      $this->data = $storedData;
      $this->is_completefetch = true;
      $this->do_store = false;
      return;
    }

    $r = $this->template();
    $name_processed = false;

    if($this->address == 'rrrrrrrrrrrrrrrrrrrrrhoLvTp') {
      $r['name'] = 'ACCOUNT_ZERO';
      $this->data = $r;
      return;
    }
    if($this->address == 'rrrrrrrrrrrrrrrrrrrrBZbvji') {
      $r['name'] = 'ACCOUNT_ONE';
      $this->data = $r;
      return;
    }
    if($this->address == 'rrrrrrrrrrrrrrrrrNAMEtxvNvQ') {
      $r['name'] = 'Ripple Name black-hole';
      $this->data = $r;
      return;
    }
    if($this->address == 'rrrrrrrrrrrrrrrrrrrn5RM1rHd') {
      $r['name'] = 'ACCOUNT_NAN';
      $this->data = $r;
      return;
    }

    //Check if is genesis (genesis flag + name)
    $genesis_name = config_static('xrpl.genesis_'.config('xrpl.net').'.'.$this->address);
    if($genesis_name !== null) {
      $r['genesis'] = true;
      $r['name'] = $genesis_name;
      $name_processed = true;
    }

    //Check if is known (name only)
    $known_name = config_static('xrpl.known_'.config('xrpl.net').'.'.$this->address);
    if($known_name !== null) {
      $r['name'] = $known_name;
      $name_processed = true;
    }

    //Avatar check start:
    //Check if there is known genesis avatar included in this project
    if(\is_file(resource_path('static/avatars/'.$this->address.'.webp'))) {
      $r['avatar'] = route('static.avatar_serve',['address' => $this->address]);
    }
    //Avatar check end
    
    $result = null;
    $fails = [
      'xrpscan_account_info' => false,
      'xumm_account_info' => false,
    ];

    # Get ledger data
    $account_data = app(XRPLWinApiClient::class)->api('account_info')
      ->params([
        'account' => $this->address,
        'strict' => true
      ])->send();
    if($account_data->isSuccess()) {
      $result = $account_data->finalResult();
    } else {
      if($account_data->result()->result->error == 'actNotFound') {
        //404 - account not found in ledger, never activated or deleted
      } else {
        throw new \Exception('Unable to fetch XRPL data');
        //422 - Unprocessable Content - try later
      }
    }

    
    /*if($result === null) {
      $this->data = $r;
      return;
    }*/

    # We have ledger data, fill them into $r
    if($result !== null) {
      $r['active'] = true;
      $r['xrp'] = $result->Balance;
      $r['flags'] = $result->Flags;
      if(isset($result->RegularKey)) {
        $r['rk'] = $result->RegularKey;
        if($r['rk'] == 'rrrrrrrrrrrrrrrrrrrrBZbvji' || $r['rk'] == 'rrrrrrrrrrrrrrrrrrrrrhoLvTp')
          $r['bh'] = true;
      }
        
      if(isset($result->Domain))
        $r['domain'] = \hex2bin($result->Domain);
      if(isset($result->EmailHash))
        $r['emailhash'] = $result->EmailHash;
    }
    
    
    # Check if is issuer from pre-synced list of issuers, possible to get kyc
    # - XRPL Notice: Some public servers disable this API method because it can require a large amount of processing.

    //$kyc_processed = false;
    
    
    $Issuer = Issuer::where('issuer',$this->address)->first();
    if($Issuer) {
      if($r['name'] == null)
        $r['name'] = $Issuer->title;
      $r['kyc'] = $Issuer->is_kyc;
      $r['issuer'] = true;
      $r['active'] = true;
      if($Issuer->social_twitter)
        $r['socials']['twitter'] = $Issuer->social_twitter;
      $name_processed = true;
    }

    # Get name,twitter from bithomp and/or xrpscan
    # xrpscan: https://api.xrpscan.com/api/v1/account/rwpTh9DDa52XkM9nTKp2QrJuCGV5d1mQVP (rate limited with 429 response code)

    if($name_processed === false) {
      $xrpscan_account_info = null;
      try {
        $xrpscan_account_info = $this->xrpscan_account_info();
      } catch (\Exception $e) {
        //try again later
        $fails['xrpscan_account_info'] = true;
      }
      if($xrpscan_account_info) {
        if($result === null) {
          //this is unexisting account, check if it is deleted by checking parentName
          if(isset($xrpscan_account_info['parentName']) && $xrpscan_account_info['parentName'] !== null) {
            $r['deleted'] = true; //has parent name, it was once activated
          }
        } else {
          if(isset($xrpscan_account_info['accountName']) && $xrpscan_account_info['accountName'] !== null) {
            if(isset($xrpscan_account_info['accountName']['name']) && $xrpscan_account_info['accountName']['name'])
              $r['name'] = (string)$xrpscan_account_info['accountName']['name'];
            if(isset($xrpscan_account_info['accountName']['twitter']) && $xrpscan_account_info['accountName']['twitter'])
              $r['socials']['twitter'] = (string)$xrpscan_account_info['accountName']['twitter'];
            //inception here?
          }
        }
      }
    }
   
    # Get KYC info from xumm API via xrpscan (only for active accounts)
    # https://api.xrpscan.com/api/v1/account/rwpTh9DDa52XkM9nTKp2QrJuCGV5d1mQVP/xummkyc (rate limited with 429 response code)
    /*if($result !== null && $kyc_processed === false) {
      $xrpscan_kyc_info = null;
      try {
        $xrpscan_kyc_info = $this->xrpscan_kyc_info();
      } catch (\Exception $e) {
        //try again later
      }
      if($xrpscan_kyc_info) {
        $r['kyc'] = $xrpscan_kyc_info['kycApproved'];
      }
    }*/
    
    # XUMM METADATA
    # 1. Alerts (get blocked status from xumm metadata)
    #    https://xumm.readme.io/reference/account-metaaccount
    #    Sample blocked raddress: rXXctAxw664pASPuHaKStZdVVKMrMFfxa
    # 2. Name from xummProfile (if not already filled)
    # 3. Paystring
    # 4. Xumm KYC

    $xumm_account_info = null;
    try {
      $xumm_account_info = $this->xumm_account_info();
    } catch (\Exception $e) {
      //try again later
      $fails['xumm_account_info'] = true;
    }

    if($xumm_account_info) {

      # 1. Alerts
      $r['alert'] = (bool)$xumm_account_info['blocked'];
      # 2. Name
      if($r['name'] == null) {
        //try to get name from xumm profile
        if(isset($xumm_account_info['xummProfile']['accountAlias'])) {
          $r['name'] = (string)$xumm_account_info['xummProfile']['accountAlias'];
        }
      }
      # 3. Paystring
      if(isset($xumm_account_info['xummProfile']['payString'])) {
        $r['paystring'] = (string)$xumm_account_info['xummProfile']['payString'];
      }
      # 4. KYC
      $r['kyc'] = (bool)$xumm_account_info['kycApproved'];
      
    }

    # Avatar
    if($r['avatar'] === null) {
      $r['avatar'] = $this->getXummCustomAvatarUrl();
      if($r['avatar'] === null && $r['emailhash'] !== null) {
        $gravatarInfo = $this->getGravatarInformation($r['emailhash']);
        if($gravatarInfo !== null) {
          $r['avatar'] = 'https://secure.gravatar.com/avatar/'.\strtolower($r['emailhash']);
          if($gravatarInfo['username'] !== null && $r['name'] === null) {
            $r['name'] = $gravatarInfo['username'];
          }
        }
      }
    }

    $this->data = $r;
    //if nothing failed, save to disk else output with small http cache
    if($fails['xrpscan_account_info'] == false && $fails['xumm_account_info'] == false) {
      //nothing failed, save to disk
      $this->is_completefetch = true;
    }
   
  }

  /**
   * @return array [bool avatar (always true), ?string username]
   */
  private function getGravatarInformation(string $emailhash): ?array
  {
    $client = new \GuzzleHttp\Client();
    try {
      $response = $client->request('GET', 'https://en.gravatar.com/'.\strtolower($emailhash).'.json', [
        //'on_headers' => self::class . '::checkIfXummHasCustomAvatar_onheader',
        'connect_timeout' => 20, //60
        'timeout'  => 20,
        'allow_redirects' => false,
        'http_errors' => false,
        'accept' => 'application/json',
      ]);
    } catch (ConnectException|TooManyRedirectsException $e) {
      //fallback to another provider recommended
      return null;
    } catch (GuzzleException $e) {
      return null;
    }

    if($response->getStatusCode() == 200) {
      $r = [
        'avatar' => true,
        'username' => null,
      ];
      $content = \json_decode($response->getBody(),true);
      if(isset($content['entry']) && \is_array($content['entry'])) {
        foreach($content['entry'] as $ent) {
          if(isset($ent['displayName']) && \is_string($ent['displayName'])) {
            $r['username'] = $ent['displayName'];
          } else if(isset($ent['preferredUsername']) && \is_string($ent['preferredUsername'])) {
            $r['username'] = $ent['preferredUsername'];
          }
          break;
        }
      }
      return $r;
    }
      
    return null;
  }

  /**
   * If avatar redirects then custom avatar is here, and we can download it, 200 = hashicon
   * Syntax: https://xumm.app/avatar/rAccount.png
   * @return ?string
   */
  private function getXummCustomAvatarUrl(): ?string
  {
    $client = new \GuzzleHttp\Client();

    try {
      $response = $client->request('GET', 'https://xumm.app/avatar/'.$this->address.'.png', [
        //'on_headers' => self::class . '::checkIfXummHasCustomAvatar_onheader',
        'connect_timeout' => 20, //60
        'timeout'  => 20,
        'allow_redirects' => false,
        'http_errors' => false
      ]);
    } catch (ConnectException|TooManyRedirectsException $e) {
      //fallback to another provider recommended
      return null;
    } catch (GuzzleException $e) {
      return null;
    }

    if($response->getStatusCode() == 302)
      return 'https://xumm.app/avatar/'.$this->address.'.png';
    return null;
  }

  //public static function checkIfXummHasCustomAvatar_onheader(ResponseInterface $response)
  //{
  //  //
  //}

  private function xumm_account_info(): array
  {
    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://xumm.app/api/v1/platform/account-meta/'.$this->address, [
      'http_errors' => false,
      'headers' => [
        'accept' => 'application/json',
      ],
    ]);
    $status_code = $response->getStatusCode();
    
    if($status_code == 429)
      throw new \Exception('Rate limited');

    if($status_code != 200)
      throw new \Exception('Service down');
    
    return \json_decode($response->getBody(),true);
  }

  private function xrpscan_account_info(): ?array
  {
    $api_url = config('xrpl.'.config('xrpl.net').'.api_xrpscan');

    if($api_url === null)
      return null; //disabled

    //dd('https://api.xrpscan.com/api/v1/account/'.$this->address);
    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', $api_url.'/api/v1/account/'.$this->address, [
      'http_errors' => false,
      'headers' => [
        'accept' => 'application/json',
      ],
    ]);
    $status_code = $response->getStatusCode();
    
    if($status_code == 429)
      throw new \Exception('Rate limited');

    if($status_code != 200)
      throw new \Exception('Service down');
    
    return \json_decode($response->getBody(),true);
  }

  /*private function xrpscan_kyc_info(): array
  {
    $client = new \GuzzleHttp\Client();
    $response = $client->request('GET', 'https://api.xrpscan.com/api/v1/account/'.$this->address.'/xummkyc', [
      'http_errors' => false,
      'headers' => [
        'accept' => 'application/json',
      ],
    ]);
    $status_code = $response->getStatusCode();

    if($status_code == 429)
      throw new \Exception('Rate limited');

    if($status_code != 200)
      throw new \Exception('Service down');
    
    return \json_decode($response->getBody(),true);
  }*/

 

  private function template(): array
  {
    return [
      'active' => false, //this might mean account was deleted or never activated
      'deleted' => false, //if true, then this account was once active (xrpscan provides this info)
      'genesis' => false,
      'name' => null, //try extract name from bithomp, xrpscan, etc
      'avatar' => null, //avatar url or null (use hashicon then)
      'paystring' => null, //available in xumm profile
      'alert' => false, //if alert is true then this account might have engaged in some kind of scam in the past, xumm blocked field
      'kyc' => false, //from various sources, essencially xumm kyc
      'issuer' => false,
      'bh' => false, //blackholed or not
      'xrp' => '0', //how much xrp this account holds at the time of data fetching in drops
      'flags' => 0,
      'rk' => null, //regular key (bk is calculated from this field)
      'domain' => null,
      'emailhash' => null,
      'socials' => [], //xrpldata.com, xrpscan
      //'genesis' => false
    ];
  }

  
}