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
  private int $_limit = 5000; //1000

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
    $last_synced_ctid_uint64 = bchexdec(encodeCTID($synctracker->last_l,65535,config('xrpl.'.config('xrpl.net').'.networkid'))); //max form this ledger 65535

    //$last_synced_ledger_index = $synctracker->last_l;
    //$last_processed_ledger_index = Tracker::getInt('aggrhooktx',0);
    $last_processed_ctid_uint64 = Tracker::getUInt64('aggrhooktx',0);

    //$select = []; //todo and put below to first param
    //DB::connection()->enableQueryLog();
    $txs = BHookTransaction::repo_fetch(null,[
        ['ctid','>',$last_processed_ctid_uint64],
        ['ctid','<=',$last_synced_ctid_uint64]
      ],['ctid','asc'],$this->_limit,0); //limit 1000
    //$this->info(\json_encode(DB::getQueryLog(),JSON_PRETTY_PRINT));exit;
    //Reject last ledger index:
    if($txs->count()) {
      $reject_ctid = $txs->last()->ctid; //we reject last 'ctid' due to possible incomplete dataset, it will be fetched next time in full

      #DB::connection()->enableQueryLog();
      DB::beginTransaction();
      $i = 0;
      //$prevtx = null;
      foreach($txs as $tx) {
        if($txs->count() == $this->_limit && $tx->ctid == $reject_ctid) break; //last ctid we do not process this batch if dataset is full
        $this->handleUninstalledHooks($tx);
        $this->aggrTx($tx);
        $last_processed_ctid_uint64 = $tx->ctid;
        //$prevtx = $tx;
        $i++;
      }
      $this->postProcessMetric();

      //Exec save on all loaded hook models
      foreach($this->_mem_hookmodels as $hm) {
        $hm->save();
      }
      $this->info('Processed '.$i.' hook transactions last ctid: '.$last_processed_ctid_uint64);
      Tracker::saveUInt64('aggrhooktx',$last_processed_ctid_uint64);
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
        //['l','<=',$tx->l], //<= because ledger 6074486 (install and uninstall on same ledger)
        ['ctid','<=',$tx->ctid],
        //['tcode',['tesSUCCESS']]
      ], //AND
      ['ctid','desc'], //sort
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
    //$this->info($tx->hook.'-'.$tx->ctid);
    $hookDef = $this->_getHookModel($tx->hook,$tx->ctid);
    if(!$hookDef)
      throw new \Exception('Unable to find hook definiton in aggrTx '.$tx->hook.' ctid:'.$tx->ctid);

    //$this->info($hookDef->ctid_from.' ('.$tx->ctid.')');
    $metric = MetricHook::where('hook',$tx->hook)->where('hook_ctid',$hookDef->ctid_from)->whereDay('day', $tx->t)->first();
    
    if(!$metric)
    {
      $metric = new MetricHook;
      $metric->hook = $tx->hook;
      $metric->hook_ctid = $hookDef->ctid_from; //hook version
      $metric->day = $tx->t;
    }

    $metric->ctid_last = $tx->ctid;

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

    //test:
    //php artisan xwa:continuoussyncproc 1471304 1471305
    //php artisan xwa:aggrhooktransactions

    
    $metrics = MetricHook::select('id','hook','hook_ctid','ctid_last','day','num_active_installs','num_installs','num_uninstalls','num_exec','num_exec_accepts','num_exec_rollbacks','num_exec_other')
      ->where('is_processed', false)
      ->orderBy('id','asc')
      ->limit(($this->_limit+1))
      ->get();
    foreach($metrics as $metric)
    {
      
      $hookDef = $this->_getHookModel($metric->hook,$metric->hook_ctid);
      if(!$hookDef)
        throw new \Exception('Unable to find hook definiton in postProcessMetric '.$metric->hook.' ctid64:'.$metric->hook_ctid);

      $ANDTEMPLATE = [
        ['hook',$metric->hook],
        ['ctid','>=',$metric->hook_ctid],
        ['ctid','<=',$metric->ctid_last],
        //['t','<=',$metric->day->format('Y-m-d').' 23:59:59'],
      ];
      //Count all rows where hookaction = 3 (install) OR 34 (install and uninstall)
      $AND = $ANDTEMPLATE;
      $AND[] = ['hookaction',[3,34]];
      $countInstalls = BHookTransaction::repo_count($AND);
      unset($AND);

      //Count all rows where hookaction = 4 (uninstalled)
      $AND2 = $ANDTEMPLATE;
      $AND2[] = ['hookaction',4];
      $countUninstalls = BHookTransaction::repo_count($AND2);
      unset($AND2);

      //note: if hook was destroyed and then created in same ledger, we might have extra count above
      //sample ledger of this is on xahau testnet: 1471305

      $numActive = $countInstalls - $countUninstalls;
      if($numActive < 0) { //hook version breakpoint detected (in same LI)
        throw new \Exception('ERROR in postProcessMetric: Install and uninstall diff is less than 0');
      }
      $metric->num_active_installs = $numActive;
      $metric->is_processed = true;
      $metric->save();
      unset($count);

      $hookDef->stat_active_installs = $numActive;
      //Part 2 - update Hook hooks.stat_* fields by counting daily metrics (easier for DB!)
      $hookDef->stat_installs   = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_installs');
      $hookDef->stat_uninstalls = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_uninstalls');
      $hookDef->stat_exec       = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_exec');
      
      $hookDef->stat_exec_accepts   = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_exec_accepts');
      $hookDef->stat_exec_rollbacks = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_exec_rollbacks');
      $hookDef->stat_exec_other     = MetricHook::where('hook',$metric->hook)->where('hook_ctid',$metric->hook_ctid)->sum('num_exec_other');
      $hookDef->save();
      
    }
  }


  private function _getHookModel(string $hook, string $ctid64): ?BHook
  {
    $ctid = bcdechex($ctid64);

    $test = HookLoader::getClosestByHash($hook,$ctid);
    //dd($ctid64,$test);
    Cache::tags(['hook'.$hook])->flush();
    
    $k = $hook.'_'.$ctid;
    if(!isset($this->_mem_hookmodels[$k])) {
      $hm = HookLoader::get($hook,$ctid,false);
      if(!$hm) {
        //load closest to the provided ledger_index
        $hm = HookLoader::getClosestByHash($hook,$ctid);
      }
      $this->_mem_hookmodels[$k] = $hm;
    }
    return $this->_mem_hookmodels[$k];


    /*

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
    */
  }
}
