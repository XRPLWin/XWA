<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Amm;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Synctracker;
use App\Models\Tracker;
use XRPLWin\XRPLLedgerTime\XRPLLedgerTimeSyncer;
use Carbon\Carbon;
use XRPLWin\XRPL\Client;

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

    set_time_limit(290); //less than 5 min max execution time, lock is 10 min, schedule is every 5 min

    $this->debug = config('app.debug');
    $this->debug_id = \substr(\md5(rand(1,999).\time()),0,5);

    $skipquery = (int)$this->option('skipquery'); //int

    # Get ultimate last ledger_index that is synced
    $synctracker = Synctracker::select('last_lt')->where('is_completed',true)->orderBy('first_l','asc')->first();
    if(!$synctracker) {
      $this->error('Nothing synced yet');
      return self::FAILURE;
    }

    if(!$skipquery)
      $this->pullAmmCreatesFromXWADB($synctracker);
    else
      $this->info('Skipping pullAmmCreatesFromXWADB()');
    
    $this->syncAmmsFromLedger();
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

    //Following query will run atleast 2 mins
    $txs = DB::table(transactions_db_name($last_processed_YM))
      ->select('address','l','t','h','i','c','i2','c2')
      ->where('xwatype',51)
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
        $amm->i1 = null;
      } else {
        $pairhash[] = $tx->c;
        $pairhash[] = $tx->i;
        $amm->c1 = $tx->c;
        $amm->i1 = $tx->i;
      }

      if($tx->i2 == null) {
        $pairhash[] = 'XRP';
        $amm->c2 = 'XRP';
        $amm->i2 = null;
      } else {
        $pairhash[] = $tx->c2;
        $pairhash[] = $tx->i2;
        $amm->c2 = $tx->c2;
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

    $limit = 50; //10 amms to sync from xrpl
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
      $this->log('Pool '.$amm->accountid.' synced');
    }
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
