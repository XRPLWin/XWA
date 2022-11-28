<?php

namespace App\Models;

use App\Repository\AccountsRepository;


use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

class BAccount extends B
{
  protected $table = 'accounts';
  protected $primaryKey = 'address';
  protected $keyType = 'string';
  public $timestamps = false;
  public string $repositoryclass = AccountsRepository::class;

  public $fillable = [
    'address', //Primary Key
    'l',
    'activatedBy',
    'isdeleted'
  ];

  const BQCASTS = [
    'address' => 'STRING',
    'l'       => 'INTEGER',
    'activatedBy' => 'NULLABLE STRING',
    'isdeleted' => 'BOOLEAN'
  ];

  public static function boot()
  {
      parent::boot();
      static::saved(function (BAccount $model) {
        $model->flushCache();
      });
      static::deleted(function (BAccount $model) {
        $model->flushCache();
      });
  }

  public function flushCache()
  {
    Cache::forget('daccount:'.$this->address);
    Cache::forget('daccount_fti:'.$this->address);
  }

  protected function bqPrimaryKeyCondition(): string
  {
    return 'address = """'.$this->address.'"""';
  }

  public static function find(string $address): ?self
  {
    $data = AccountsRepository::fetchByAddress($address);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function insert(array $values): bool
  {
    return AccountsRepository::insert($values);
  }

  /**
   * @return ?array [ 'repoch' => <ripple epoch>, 'date' => YYYY-MM-DD, 'li' => SK ]
   */
  private function getFirstTransactionInfo(string $txTypeNamepart): ?array
  {
    $result = null;
    $TransactionModelName = '\\App\\Models\\BTransaction'.$txTypeNamepart;
    $Model = new $TransactionModelName;
  

    //$repository = new $Model->repositoryclass;
    $r = $Model->repositoryclass::fetchOne('PK = """'.$this->address.'-'.$TransactionModelName::TYPE.'""" AND SK > 0','SK,t','SK ASC');
    if($r !== null) {
      $result = [
        'repoch' => $r->t, //ripple epoch
        'date' => ripple_epoch_to_carbon($r->t)->format('Y-m-d'),
        'li' => $r->SK
      ];
    }
    return $result;
    /*dd($result);


    dd($txTypeNamepart);
    $result = null;
    $TransactionModelName = '\\App\\Models\\BTransaction'.$txTypeNamepart;
    $r = $TransactionModelName::createContextInstance($this->PK)->where('PK', $this->PK.'-'.$TransactionModelName::TYPE)->where('SK', '>', 0)->take(1)->get(['t'])->first(); //['PK','SK','t']
    if($r) {
      $result = [
        'repoch' => $r->t, //ripple epoch
        'date' => ripple_epoch_to_carbon($r->t)->format('Y-m-d'),
        'li' => $r->SK
      ];
    }
      
    return $result;*/
  }

  /**
   * Cached
   * @return [ 'first' => YYYY-MM-DD, 'first_per_types' => [ <DTransaction::TYPE> =>  [ repoch, date, li ], ... ] ]
   */
  public function getFirstTransactionAllInfo(): array
  {
    $cache_key = 'daccount_fti:'.$this->address;
    
    $r = Cache::get($cache_key);

    if($r === null) {
      $typeList = config('xwa.transaction_types');
      
      $repoch_tracker = null;
  
      $r = [
        'first' => null, //time of first transaciton
        'first_per_types' => [],   //times of first transaction per types
      ];
  
      foreach($typeList as $typeIdentifier => $typeName) {
        $t = $this->getFirstTransactionInfo($typeName);
        $r['first_per_types'][$typeIdentifier] = $t;
        if($t !== null) {
          if($repoch_tracker  === null) {  //initial
            $repoch_tracker = $t['repoch'];
            $r['first'] = $t['date'];
          }
          if($t['repoch'] < $repoch_tracker) { //check is lesser
            $repoch_tracker = $t['repoch'];
            $r['first'] = $t['date'];
          }
        }
        unset($t);
      }
      unset($repoch_tracker);
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    return $r;
  }

  /**
   * Queries XRPLedger to determine if this account is issuer.
   * @return bool
   */
  /*public function checkIsIssuer(): bool
  {
    //get if this account is issuer or not by checking obligations
    $gateway_balances = app(XRPLWinApiClient::class)
        ->api('gateway_balances')
        ->params([
            'account' => $this->address,
            'strict' => true,
            'ledger_index' => 'validated',
        ])
        ->send()
        ->finalResult();

    if(isset($gateway_balances->obligations) && !empty($gateway_balances->obligations))
      return true;

    return false;
  }*/
}
