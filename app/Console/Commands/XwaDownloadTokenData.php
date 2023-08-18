<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use File;
use JsonMachine\Items;
use App\Models\Token;
use App\Models\Issuer;

#use GuzzleHttp\Subscriber\Oauth\Oauth1;

class XwaDownloadTokenData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:downloadtokendata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downloads token data (daily) from xrpldata.com';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      $this->_download();
      $path = Storage::disk('local')->path('import/tokens/tokens.json');

      if(!is_file($path))
      {
        throw new \Exception('File does not exist: '.$path);
      }

      //Truncate tables: tokens, issuers
      Token::query()->truncate();
      Issuer::query()->truncate();
      $this->info('Tables truncated');

      //extract tokens to local database for faster info fetching
      $datasetream = Items::fromFile($path,['pointer' => '/issuers']);

      $i = 0;
      foreach($datasetream as $d)
      {
        $i++;
        $this->info('Issuer '.$i.'.');
        $issuer = $d->data->account;
        $this->info('   '.$issuer);
        //dd($d);
        $issuerModel = new Issuer;
        $issuerModel->title = null;
        if(isset($d->data->username))
          $issuerModel->title = (string)$d->data->username !== '' ? (string)$d->data->username:null;
        $issuerModel->issuer = $issuer;
        $issuerModel->is_verified = isset($d->data->verified) ? (bool)$d->data->verified:false;
        $issuerModel->is_kyc = isset($d->data->kyc) ? (bool)$d->data->kyc:false;
        if(isset($d->data->twitter))
        $issuerModel->social_twitter = null;
        if(isset($d->data->twitter))
          $issuerModel->social_twitter = (string)$d->data->twitter !== '' ? (string)$d->data->twitter:null;
        $issuerModel->save();

        $tokens = $d->tokens;
        foreach($d->tokens as $t)
        {
          $tokenModel = new Token;
          $tokenModel->issuer_id = $issuerModel->id;
          $tokenModel->currency = $t->currency;
          $tokenModel->amount = $t->amount;
          $tokenModel->num_trustlines = $t->trustlines;
          $tokenModel->num_holders = $t->holders;
          $tokenModel->num_offers = $t->offers;
          $tokenModel->self_assessment_url = null;
          if(isset($t->self_assessment->information) && \is_string($t->self_assessment->information))
            $tokenModel->self_assessment_url = $t->self_assessment->information;
          $tokenModel->save();
          $this->info('  - '.xrp_currency_to_symbol($t->currency));
        }


      }


      return Command::SUCCESS;
    }

    private function _download()
    {
      $client = new \GuzzleHttp\Client();
      $path = Storage::disk('local')->path('import/tokens');
      if (!is_dir($path))
         File::makeDirectory($path, 0775, true);

      $client->request('GET', 'https://api.xrpldata.com/api/v1/tokens', ['sink' => $path.'/tokens.json']); //will overwrite existing json

    }

}
