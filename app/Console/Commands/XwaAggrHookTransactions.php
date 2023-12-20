<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BHookTransaction;
use App\Models\Synctracker;
use App\Models\Tracker;
use App\Models\MetricHook;
use App\Models\BHook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Utilities\HookLoader;

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
   * A place to keep models in memory and referenced
   */
  private array $_mem_hookmodels = [];
  
  /**
   * How much transactions to process per job?
   */
  private int $_limit = 10000; //1000

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
      //$prevtx = null;
      foreach($txs as $tx) {
        if($txs->count() == $this->_limit && $tx->l == $reject_l) break; //last l we do not process this batch if dataset is full
        $this->handleUninstalledHooks($tx);
        $this->aggrTx($tx);
        $last_processed_ledger_index = $tx->l;
        //$prevtx = $tx;
        $i++;
      }
      $this->postProcessMetric();

      //Exec save on all loaded hook models
      foreach($this->_mem_hookmodels as $hm) {
        $hm->save();
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
        ['hookaction', [3,34]],
        ['l','<=',$tx->l], //<= because ledger 6074486 (install and uninstall on same ledger)
        ['tcode','tesSUCCESS']
      ], //AND
      ['l','desc'], //sort
      1, //limit
      0 //offset
    );

    if(!$prev->count())
      throw new \Exception('Prev hook record not found for '.$tx->hook.' acc '.$tx->r);
    $prev = $prev->first();

    $prev->hookaction = 34;
    $prev->save();
  }

  /**
   * Collect and store statistics
   * Store aggragation (daily) data to metric_hooks table.
   * @param BHookTransaction $tx - transaction to process
   * @return void
   */
  private function aggrTx(BHookTransaction $tx): void
  {
    $hookDef = $this->_getHookModel($tx->hook,$tx->l);
    if(!$hookDef)
      throw new \Exception('Unable to find hook definiton in aggrTx '.$tx->hook.' li:'.$tx->l);

    $metric = MetricHook::where('hook',$tx->hook)->where('l',$hookDef->l_from)->whereDay('day', $tx->t)->first();
    
    if(!$metric)
    {
      $metric = new MetricHook;
      $metric->hook = $tx->hook;
      $metric->l = $hookDef->l_from; //hook version
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
  }

  /**
   * Runs once per job, handles metrics with is_processed=0
   */
  private function postProcessMetric()
  {
    $metrics = MetricHook::select('id','hook','l','day')->where('is_processed', false)->orderBy('id','asc')->limit(1000)->get();
    foreach($metrics as $metric)
    {
      $hookDef = $this->_getHookModel($metric->hook,$metric->l);
      if(!$hookDef)
        throw new \Exception('Unable to find hook definiton in postProcessMetric '.$metric->hook.' li:'.$metric->l);
      //Count all rows where hookaction = 3 (install) but not 34 (install and uninstall)
      $countInstalls = BHookTransaction::repo_count(
        [
          ['hook',$metric->hook],
          ['hookaction',[3,34]],
          ['tcode','tesSUCCESS'],
          ['l','>=',$metric->l],
          //todo end range l from hookDef 
          //['t','>=',$Ymd.' 00:00:00'],
          ['t','<=',$metric->day->format('Y-m-d').' 23:59:59'],
        ]
      );
      $countUninstalls = BHookTransaction::repo_count(
        [
          ['hook',$metric->hook],
          ['hookaction',4],
          ['tcode','tesSUCCESS'],
          ['l','>=',$metric->l],
          //['t','>=',$Ymd.' 00:00:00'],
          ['t','<=',$metric->day->format('Y-m-d').' 23:59:59'],
        ]
      );

      $numActive = $countInstalls - $countUninstalls;
      
      $metric->num_active_installs = $numActive;
      $metric->is_processed = true;
      $metric->save();
      unset($count);

      //Part 2 - update Hook hooks.stat_* fields - all affected hooks
      //foreach($affectedHooks as $affectedHook) {
      //  $HookDef = $this->_getHookModel($affectedHook,)
      //}
    }
  }


  private function _getHookModel($hook, $ledger_index): ?BHook
  {
    Cache::tags(['hook'.$hook])->flush();
    
    $k = $hook.'_'.$ledger_index;
    if(!isset($this->_mem_hookmodels[$k])) {
      $hm = HookLoader::get($hook,$ledger_index,false);
      if(!$hm) {
        //load closest to the provided ledger_index
        $hm = HookLoader::getClosestByHash($hook,$ledger_index);
      }
      $this->_mem_hookmodels[$k] = $hm;
    }
    return $this->_mem_hookmodels[$k];
  }
}
