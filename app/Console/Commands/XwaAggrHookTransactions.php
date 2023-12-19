<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BHookTransaction;
use App\Models\Synctracker;
use App\Models\Tracker;
use App\Models\MetricHook;
use Illuminate\Support\Facades\DB;

/**
 * This is scheduled job every 5 min, processes 1000 transactions
 */
class XwaAggrHookTransactions extends Command
{

  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:aggrhooktransactions';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'Process and aggregate hook transactions';

  
  /**
   * How much transactions to process per job?
   */
  private int $_limit = 1000; //1000

  public function handle()
  {
    if(config('xwa.sync_type') != 'continuous') {
      $this->info('Continous sync is not enabled, this feature in unavailable');
      return Command::SUCCESS;
    }

    set_time_limit(290); //less than 5 min max execution time, lock is 10 min, schedule is every 5 min

    # Get ultimate last ledger_index that is synced
    $synctracker = Synctracker::select('last_l')->where('is_completed',true)->orderBy('first_l','asc')->first();
    if(!$synctracker) {
      $this->error('Nothing synced yet');
      return self::FAILURE;
    }
    $last_synced_ledger_index = $synctracker->last_l;
    $last_processed_ledger_index = Tracker::getInt('aggrhooktx',0);
    
    //$select = []; //todo and put below to first param
    //DB::connection()->enableQueryLog();
    $txs = BHookTransaction::repo_fetch(null,[
        ['l','>',$last_processed_ledger_index], //0
        ['l','<=',$last_synced_ledger_index] //10
      ],['l','asc'],$this->_limit,0); //limit 1000
    //$this->info(\json_encode(DB::getQueryLog(),JSON_PRETTY_PRINT));
    //exit;

    //Reject last ledger index:
    if($txs->count()) {
      $reject_l = $txs->last()->l; //we reject last 'l' due to possible incomplete dataset, it will be fetched next time in full

      #DB::connection()->enableQueryLog();
      DB::beginTransaction();
      $i = 0;
      $prevtx = null;
      foreach($txs as $tx) {
        if($txs->count() == $this->_limit && $tx->l == $reject_l) break; //last l we do not process this batch if dataset is full
        $this->handleUninstalledHooks($tx);
        $this->aggrTx($tx,$prevtx);
        $last_processed_ledger_index = $tx->l;
        $prevtx = $tx;
        $i++;
      }
      $this->info('Processed '.$i.' hook transactions last li: '.$last_processed_ledger_index);
      Tracker::saveInt('aggrhooktx',$last_processed_ledger_index);
      DB::commit();
    }
    return self::SUCCESS;
  }

  /**
   * Change status from 3 to 34 for install transactions if user is uninstalled.
   * @param BHookTransaction $tx - transaction to process
   * @return void
   */
  private function handleUninstalledHooks(BHookTransaction $tx): void
  {
    if($tx->hookaction != 4) return; //only uninstall action

    //Find previous install action from this account
    $prev = BHookTransaction::repo_fetch(
      null, //select
      [
        ['hook',$tx->hook],
        ['r',$tx->r],
        ['hookaction',3],
        ['l','<',$tx->l],
        ['tcode','tesSUCCESS']
      ], //AND
      ['l','desc'], //sort
      1, //limit
      0 //offset
    );

    if(!$prev->count())
      throw new \Exception('Prev hook record not found for '.$tx->hook);
    $prev = $prev->first();

    $prev->hookaction = 34;
    $prev->save();
  }

  /**
   * Collect and store statistics
   * Store aggragation (daily) data to metric_hooks table.
   * @param BHookTransaction $tx - transaction to process
   * @param ?BHookTransaction $prevtx - transaction that was processed previously
   * @return void
   */
  private function aggrTx(BHookTransaction $tx,?BHookTransaction $prevtx): void
  {
    $metric = MetricHook::where('hook',$tx->hook)->whereDay('day', $tx->t)->first();
    if(!$metric)
    {
      $metric = new MetricHook;
      $metric->hook = $tx->hook;
      $metric->day = $tx->t;
    }

    # Num installs
    if($tx->hookaction == 3 || $tx->hookaction == 34) {
      $metric->num_installs++;
    }

    # Num uninstalls
    if($tx->hookaction == 4) {
      $metric->num_uninstalls++;
    }

    # Executions
    if($tx->hookaction == 0) {
      $metric->num_exec++;
      if($tx->hookresult == 3) { //accept
        $metric->num_exec_accepts++;
      } elseif($tx->hookresult == 2) { //rollback
        $metric->num_exec_rollbacks++;
      } else {
        $metric->num_exec_other++;
      }
    }
    $metric->save();

    # If this is new day, process previous day sum of active accounts:
    if($prevtx === null || ($prevtx !== null && $prevtx->t->format('Y-m-d') != $tx->t->format('Y-m-d'))) {
      $this->aggrTx_aggregateSum($tx->t->addDays(-1)->format('Y-m-d'));
    }

    //if($prevtx === null || ($prevtx !== null && dates do not match))
    #$queries = DB::getQueryLog();
    #$this->info('save'.\json_encode($metric->toArray()).\json_encode($queries,JSON_PRETTY_PRINT));
    
    
  }

  /**
   * Calculates sum of active accounts for hooks and stores them to metric_hooks.num_active_installs
   * @param string $Ymd in sql format "2023-01-20"
   */
  private function aggrTx_aggregateSum(string $Ymd)
  {
    $day = \Carbon\Carbon::parse($Ymd)->addHours(12);
    //get all rows and process them
    $metrics = MetricHook::select('id','hook')->whereDay('day', $day)->get();
    if(!$metrics->count()) return;

    foreach($metrics as $metric) {
      //Count all rows where hookaction = 3 (install) but not 34 (install and uninstall)
      $count = BHookTransaction::repo_count(
        [
          ['hook',$metric->hook],
          ['hookaction',3],
          ['tcode','tesSUCCESS'],
          ['t','>=',$Ymd.' 00:00:00'],
          ['t','<=',$Ymd.' 23:59:59'],
        ]
      );
      $metric->num_active_installs = $count;
      $metric->save();
      unset($count);
    }
  }
}
