<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Amm;
use Illuminate\Support\Facades\DB;
use App\Models\Synctracker;
use App\Models\Tracker;
use XRPLWin\XRPLLedgerTime\XRPLLedgerTimeSyncer;
use Carbon\Carbon;

class XwaAggrAmm extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:aggramm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregates AMM transactions and stores them in amms table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
      if(config('xwa.sync_type') != 'continuous') {
        $this->info('Continous sync is not enabled, this feature in unavailable');
        return Command::SUCCESS;
      }

      if(config('xwa.database_engine') != 'sql') {
        $this->info('SQL database engine is not enabled, this feature in unavailable');
        return Command::SUCCESS;
      }
      set_time_limit(290); //less than 5 min max execution time, lock is 10 min, schedule is every 5 min

      # Get ultimate last ledger_index that is synced
      /*$synctracker = Synctracker::select('last_l')->where('is_completed',true)->orderBy('first_l','asc')->first();
      if(!$synctracker) {
        $this->error('Nothing synced yet');
        return self::FAILURE;
      }*/
      $ledgertime = new XRPLLedgerTimeSyncer(); //init syncer
      
      $last_processed_li = Tracker::getInt('aggrammlli',config('xrpl.'.config('xrpl.net').'.genesis_ledger')); //aggr amm last ledger index
      $last_processed_YM = Tracker::getInt('aggrammlym',0); //zero (if needs recalculation) or YM when set
      $last_processed_carbon = $ledgertime->ledgerIndexToCarbon($last_processed_li);
      if($last_processed_YM == 0)
        $last_processed_YM = $last_processed_carbon->format('Ym');

      //Following query will run atleast 2 mins
      $txs = DB::table(transactions_db_name($last_processed_YM))
        ->select('address','l','t','h','i','c','i2','c2')
        ->where('xwatype',51)
        ->where('isin',true)
        ->where('l','>', $last_processed_li)
        ->orderBy('l','asc')
        ->limit(10)
        ->get();

      if(!$txs->count()) {
        //no more created amms this month
        //add +1 month, to $last_processed_YM eg 201301 to 201302:
        $last_processed_YM = Carbon::create(\substr($last_processed_YM,0,4), \substr($last_processed_YM,4,2), 11, 15, 0, 0, 'UTC')->addMonth(); //create Carbon datetime object
        $last_processed_YM = (int)$last_processed_YM->format('Ym');
        
        Tracker::saveInt('aggrammlym',$last_processed_YM);
        $this->info('No more created amms this month, saving tracker to next month: '.($last_processed_YM));
        return Command::SUCCESS;
      }


      //We have txs, loop them, save tracker at last item
      foreach($txs as $tx) {
        
        $this->info('AMM '.$tx->address.' found, saving...');
        //dd($tx);
        $amm = new Amm;
        $amm->accountid = $tx->address;
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
          //duplicated, enable it
          
          
        }
        

        

        $last_processed_li = $tx->l;
      }
      Tracker::saveInt('aggrammlli',$last_processed_li);
    }
}
