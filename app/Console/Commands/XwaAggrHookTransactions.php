<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BHookTransaction;
use App\Models\Synctracker;
use App\Models\Tracker;
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
    $txs = BHookTransaction::repo_fetch(null,[
        ['l','<=',$last_synced_ledger_index], //10
        ['l','>',$last_processed_ledger_index] //0
      ],['l','asc'],$this->_limit,0); //limit 1000

    //Reject last ledger index:
    if($txs->count()) {
      $reject_l = $txs->last()->l; //we reject last 'l' due to possible incomplete dataset, it will be fetched next time in full

      DB::beginTransaction();
      $i = 0;
      foreach($txs as $tx) {
        
        if($tx->l == $reject_l) break; //last l we do not process this batch
        $this->handleUninstalledHooks($tx);
        $this->aggrTx($tx);
        $last_processed_ledger_index = $tx->l;
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
   * Store aggragation (daily) data to seperate tables.
   */
  private function aggrTx(BHookTransaction $tx): void
  {
    
  }
}
