<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Amm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Synctracker;
use App\Models\Tracker;
use App\Models\BTransactionAMMCreate;
use Brick\Math\BigDecimal;
use XRPLWin\XRPLLedgerTime\XRPLLedgerTimeSyncer;
use Carbon\Carbon;
use XRPLWin\XRPL\Client;
use Brick\Math\BigNumber;

class XwaAggrAmm extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:aggramm {--skipquery=0}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Aggregates AMM transactions and stores them in amms table';

  /**
   * How much to pull from xwa db.
   */
  private $_limit = 1000; //10

  /**
   * When debugging enabled it will log output to log file.
   */
  private bool $debug = true;
  private string $debug_id = '';


  /**
   * Execute the console command.
   */
  public function handle()
  {
    if(config('xwa.sync_type') != 'continuous') {
      $this->info('Continuous sync is not enabled, this feature in unavailable');
      return Command::SUCCESS;
    }

    if(config('xwa.database_engine') != 'sql') {
      $this->info('SQL database engine is not enabled, this feature in unavailable');
      return Command::SUCCESS;
    }

    if(!config('xrpl.'.config('xrpl.net').'.feature_amm')) {
      $this->info('AMM Feature is not enabled');
      return Command::SUCCESS;
    }

    $this->debug = config('app.debug');
    $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);

    $skipquery = (int)$this->option('skipquery'); //int

    //test start
    //$test = Amm::where('accountid','rMEJo9H5XvTe17UoAJzj8jtKVvTRcxwngo')->first();
    //$this->syncMarketData($test);
    //exit;
    //test end

    # Get ultimate last ledger_index that is synced
    $synctracker = Synctracker::select('last_lt')->where('is_completed',true)->orderBy('first_l','asc')->first();
    if(!$synctracker) {
      $this->error('Nothing synced yet');
      return self::FAILURE;
    }

    /**
     * int $what - 0 will run query, 1,2,3 will run xrpl sync
     * Every third run $what will be 0
     * 0,1,2,3, 0,1,2,3...
     */
    $what = Tracker::getInt('aggrammwht',0);
    $this->info('What is: '.$what);
    //Increment $what
    $toSaveWhat = $what+1;
    if($toSaveWhat > 3) $toSaveWhat = 0;
    Tracker::saveInt('aggrammwht',$toSaveWhat);

    if(!$skipquery) {
      if($what == 0) $this->pullAmmCreatesFromXWADB($synctracker);
    } else $this->info('Skipping pullAmmCreatesFromXWADB()');

    if($what != 0) $this->syncAmmsFromLedger();
  }

  private function pullAmmCreatesFromXWADB(Synctracker $synctracker)
  {
    $last_processed_li = Tracker::getInt('aggrammlli',config('xrpl.'.config('xrpl.net').'.genesis_ledger')); //aggr amm last ledger index
    $last_processed_YM = Tracker::getInt('aggrammlym',202401); //zero (if needs recalculation) or YM when set
    
    //$ledgertime = new XRPLLedgerTimeSyncer(); //init syncer
    //$last_processed_carbon = $ledgertime->ledgerIndexToCarbon($last_processed_li);
    //if($last_processed_YM == 0)
    //  $last_processed_YM = $last_processed_carbon->format('Ym');
    //dd($last_processed_YM);
    $this->log('Month: '.$last_processed_YM);
    $this->log('Starting slow query, this may take few minutes...');

    //Following query will run atleast few mins
    $txs = DB::table(transactions_db_name($last_processed_YM))
      ->select('address','l','t','h','i','c','i2','c2')
      ->where('xwatype',BTransactionAMMCreate::TYPE)
      ->where('isin',true)
      ->where('l','>', $last_processed_li)
      ->orderBy('l','asc')
      ->limit($this->_limit)
      ->get();

    if(!$txs->count()) {
      //no more created amms this month?
      //Two scenarios: no more this month, to to next month
      //  or this month is not synced fully yet, in that case exit here.

      //add +1 month, to $last_processed_YM eg 201301 to 201302:
      $last_processed_YM = Carbon::create(\substr($last_processed_YM,0,4), \substr($last_processed_YM,4,2), 11, 15, 0, 0, 'UTC')->addMonth(); //create Carbon datetime object
      $last_processed_YM = (int)$last_processed_YM->format('Ym');
      $synctracker_current_YM = (int)$synctracker->last_lt->format('Ym');
      //Check if this month is fully synced
      $canSwitchToNextMonth = $synctracker_current_YM >= $last_processed_YM;
      if(!$canSwitchToNextMonth) {
        $this->log('Unable to switch to next month: '.$synctracker_current_YM.' >= '.$last_processed_YM.' (last synced YM >= last processed YM)');
        return Command::SUCCESS;
      }
      Tracker::saveInt('aggrammlym',$last_processed_YM);
      $this->log('No more created amms this month, saving tracker to next month: '.($last_processed_YM));
      return Command::SUCCESS;
    }


    //We have txs, loop them, save tracker at last item
    foreach($txs as $tx) {
      
      $this->info('AMM '.$tx->address.' found, saving...');
      //dd($tx);
      $amm = new Amm;
      $amm->accountid = $tx->address;
      $amm->synced_at = now()->addDays(-30); //past date will trigger ledger sync
      $pairhash = [];
      if($tx->i == null) {
        $pairhash[] = 'XRP';
        $amm->c1 = 'XRP';
        $amm->c1_display = 'XRP';
        $amm->i1 = null;
      } else {
        $pairhash[] = $tx->c;
        $pairhash[] = $tx->i;
        $amm->c1 = $tx->c;
        $amm->c1_display = xrp_currency_to_symbol($tx->c);
        $amm->i1 = $tx->i;
      }

      if($tx->i2 == null) {
        $pairhash[] = 'XRP';
        $amm->c2 = 'XRP';
        $amm->c2_display = 'XRP';
        $amm->i2 = null;
      } else {
        $pairhash[] = $tx->c2;
        $pairhash[] = $tx->i2;
        $amm->c2 = $tx->c2;
        $amm->c2_display = xrp_currency_to_symbol($tx->c2);
        $amm->i2 = $tx->i2;
      }
      \sort($pairhash,SORT_NATURAL);
      $amm->pairhash = \implode('',$pairhash);
      $amm->h = $tx->h;
      $amm->t = $tx->t; //time created
      $amm->tradingfee = 0; //fill later
      try {
        $amm->save();
      } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
        //duplicated, activate it if not activated, sync job does this automatically later anyway..
        //amm can be deleted by: AMMDelete, AMMWithdraw

        //Get old AMM, same pait but possibly old accountid
        $existingamm = Amm::select('accountid')->where('pairhash',$amm->pairhash)->first();
        if($existingamm) {
          $existingamm->delete();
          $this->log('Deleted AMM accountid (duplicated): '.$existingamm->accountid);
          $amm->save();
        }
        //throw $e;
      }
      
      $last_processed_li = $tx->l;
    }
    Tracker::saveInt('aggrammlli',$last_processed_li);
  }

  private function syncAmmsFromLedger()
  {
    $this->log('Starting ledger AMMs rolling sync');

    $limit = 200; //10 amms to sync from xrpl
    $staleMinutes = 30; //resync after data is 30 minutes old

    $XRPLClient = app(Client::class);

    $amms = Amm::select('*')
      ->where('synced_at','<', now()->addMinutes(-$staleMinutes))
      ->orderBy('synced_at','asc')
      ->limit($limit)
      ->get();

    foreach($amms as $amm) {
      $params = [
        'asset' => [],
        'asset2' => []
      ];
      if($amm->i1 == null) {
        //is XRP
        $params['asset']['currency'] = 'XRP';
      } else {
        $params['asset']['currency'] = $amm->c1;
        $params['asset']['issuer'] = $amm->i1;
      }

      if($amm->i2 == null) {
        //is XRP
        $params['asset2']['currency'] = 'XRP';
      } else {
        $params['asset2']['currency'] = $amm->c2;
        $params['asset2']['issuer'] = $amm->i2;
      }
      $tx = $XRPLClient->api('amm_info')
        ->params($params);
      try {
        $tx->send();
      } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        // Handle errors
        $this->log('');
        $this->log('Error catched: '.$e->getMessage());
        //throw $e;
      }

      $is_success = $tx->isSuccess();
      if(!$is_success) {

        //Check if deleted
        $resultArray = $tx->resultArray();
        if(isset($resultArray['result']->error) && $resultArray['result']->error == 'actNotFound') {
          $this->log('Pool '.$amm->accountid.' DELETED');
          $amm->synced_at = now();
          $amm->is_active = false;
          $amm->save();
          continue;
        }

        $this->log(\json_encode($params));
        $this->log('Unsuccessful response (skipping for now)');
        sleep(3);
        continue;
      }

      $amminfo = $tx->finalResult();

      //sync pool result to db:
      $amm->synced_at = now();
      $amm->tradingfee = (int)$amminfo->amm->trading_fee;
      $amm->a1 = $this->getAmountX($amm->c1,$amm->i1,$amminfo->amm->amount,$amminfo->amm->amount2);
      $amm->a2 = $this->getAmountX($amm->c2,$amm->i2,$amminfo->amm->amount,$amminfo->amm->amount2);
      $amm->lpc = $amminfo->amm->lp_token->currency;
      $amm->lpi = $amminfo->amm->lp_token->issuer;
      $amm->lpa = $amminfo->amm->lp_token->value;
      $amm->save();
      $this->log('Pool '.$amm->accountid.' ledger synced');

      $this->syncMarketData($amm);
      $this->log('Pool '.$amm->accountid.' market data synced');
    }
  }

  /**
   * Queries data API and synces additional market data.
   * - price of traded pair
   * - 24h change
   * - 24h volume
   * - 24h low and high
   * - tvl (total value locked)
   */
  private function syncMarketData(Amm $amm)
  {
    $data_api = config('xrpl.'.config('xrpl.net').'.data_api');
    if(!$data_api) return;

    
    try {
      $XRPValue1 = $this->getXRPValue($amm->c1,$amm->i1,$amm->a1);
      $XRPValue2 = $this->getXRPValue($amm->c2,$amm->i2,$amm->a2);
    } catch(\Throwable $e) {
      $this->log('Unable to get value for amm '.$amm->accountid.': '.$e->getMessage());
      return;
    }
    $TVL = $XRPValue1->plus($XRPValue2);
    $amm->tvl = $TVL;

    //24h data: volume, high, low
    //$data24h = $this->get24hData($amm->c1,$amm->i1,$amm->c2,$amm->i2);
    //$amm->volume24 = $data24h['volume'];
    //$amm->low24 = $data24h['low'];
    //$amm->high24 = $data24h['high'];
    
    
    //dd($data24h);



    $amm->save();

    //$endpoint = $data_api.'/v1/iou/market_data/'.$amount2.'/'.$amount1.'?interval=1h';
    //$endpoint1 = $data_api.'/v1/iou/volume_data/'.$amount1.'?interval=1h&descending=true';
    //$endpoint2 = $data_api.'/v1/iou/volume_data/'.$amount2.'?interval=1h&descending=true';

    //dd($endpoint1,$endpoint2,(string)$XRPValue1,(string)$XRPValue2,(string)$TVL);
  }
  
  /*private function get24hData(string $currency1, ?string $issuer1, string $currency2, ?string $issuer2): array
  {
    $amount1 = $issuer1 ? $issuer1.'_'.$currency1:$currency1;
    $amount2 = $issuer2 ? $issuer2.'_'.$currency2:$currency2;

    $data_api = config('xrpl.'.config('xrpl.net').'.data_api');
    $endpoint = $data_api.'/v1/iou/ticker_data/'.$amount1.'/'.$amount2.'?interval=1d&only_amm=true';

    //Make xrp first place (make it base)
    if($amount1 === 'XRP') {
      $endpoint = $data_api.'/v1/iou/ticker_data/'.$amount2.'/'.$amount1.'?interval=1d&only_amm=true';
    }
    $this->line($endpoint);
    $r = $this->queryDataAPI($endpoint);
    if(isset($r[0]->last)) {
      return [
        'volume' => $r[0]->base_volume,
        'low' => $r[0]->low,
        'high' => $r[0]->high,
      ];
    }

    return [
      'volume' => 0,
      'low' => 0,
      'high' => 0,
    ];
    //throw new \Exception('Unable to get 24h amm only data');
  }*/

  private function getXRPValue(string $currency, ?string $issuer, string $value): BigNumber 
  {
    if($issuer === null) {
      $drops = BigDecimal::of($value); //Direct Drops
      return $drops->exactlyDividedBy(1000000);
    }
    //get XRP/Currency from data api
    $data_api = config('xrpl.'.config('xrpl.net').'.data_api');

    //Try 1h
    $endpoint = $data_api.'/v1/iou/ticker_data/'.$issuer.'_'.$currency.'/XRP?interval=1h&only_amm=false';
    $r = $this->queryDataAPI($endpoint);
    
    if(isset($r[0]->last)) {
      $xrpValueSingle = BigNumber::of($r[0]->last);
      return $xrpValueSingle->multipliedBy($value);
    }

    //Try 1d if 1h fails
    $endpoint = $data_api.'/v1/iou/ticker_data/'.$issuer.'_'.$currency.'/XRP?interval=1d&only_amm=false';
    $r = $this->queryDataAPI($endpoint);
    if(isset($r[0]->last)) {
      $xrpValueSingle = BigNumber::of($r[0]->last);
      return $xrpValueSingle->multipliedBy($value);
    }
    //dd($endpoint,$r);
    throw new \Exception('Unable to get market data');
  }

  private function queryDataAPI(string $endpoint): array
  {
    $client = new \GuzzleHttp\Client(['verify' => false]);
    $res = $client->request('GET', $endpoint);
    if($res->getStatusCode() != 200) {
      throw new \Exception('Non 200 response');
    }
    $r = \json_decode((string)$res->getBody()); //stdClass
    return $r;
  }

  /**
   * Takes XWA currency and issuer and returns amount from mathec AMMAmount1 or AMMAmount2
   * @param string $c - currency from xwa to compare with amm amounts
   * @param ?string $i - issuer from xwa to compare with amm amounts
   * @param string|object $AMMAmount1 amount1 from XRPL Response object
   * @param string|object $AMMAmount2 amount1 from XRPL Response object
   * @return string
   */
  private function getAmountX(string $c, ?string $i, string|object $AMMAmount1, string|object $AMMAmount2): string
  {
    if($c === 'XRP' && $i === null) {
      //expecting XRP
      if(\is_string($AMMAmount1)) return $AMMAmount1;
      if(\is_string($AMMAmount2)) return $AMMAmount2;
    } else {
      //expecting IOU
      if(!\is_string($AMMAmount1)) {
        if($c == $AMMAmount1->currency && $i == $AMMAmount1->issuer)
          return $AMMAmount1->value;
      }
      if(!\is_string($AMMAmount2)) {
        if($c == $AMMAmount2->currency && $i == $AMMAmount2->issuer)
          return $AMMAmount2->value;
      }
    }
    throw new \Exception('Unable to match amount in getAmountX');
  }


  private function log(string $logline)
  {
    $logline = '['.$this->debug_id.'] '.$logline;
    $this->info($logline);

    if(!$this->debug)
      return;

    //Log::channel('syncjobcontinuous')->info($logline);
  }

}
