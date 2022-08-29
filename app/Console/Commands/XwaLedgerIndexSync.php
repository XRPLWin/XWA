<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Ledgerindex;
use Illuminate\Console\Command;

use XRPLWin\XRPL\Client;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Queries XRPL and finds last ledger for each day from genesis to today.
 * Data is stored in local DB, if job is re-executed it will continue from
 * last synced day. This job can be set to executed daily soon after midnight UTC.
 */
class XwaLedgerIndexSync extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'xwa:ledgerindexsync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch last ledger for each day from genesis to today and store it in local db, if localdb has per day data, that day will be skipped';

    /**
     * Current ledger being scanned.
     *
     * @var int
     */
    private int $ledger_current;

    /**
     * Last ledger index in time of this job.
     *
     * @var int
     */
    private readonly int $ledger_last;

    /**
     * XRPL API Client instance
     *
     * @var \XRPLWin\XRPL\Client
     */
    protected readonly Client $XRPLClient;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->XRPLClient = app(Client::class);
        $ledger_last_api = $this->XRPLClient->api('ledger')
        ->params([
          'ledger_index' => 'validated',
          'accounts' => false,
          'full' => false,
          'transactions' => false,
          'expand' => false,
          'owner_funds' => false,
        ]);
  
        $this->ledger_last = (int)$ledger_last_api->send()->finalResult()->ledger_index;
    }

    
    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      //Get last from DB
      $LastDb = Ledgerindex::select('ledger_index_last','day')->orderByDesc('day')->first();
      if(!$LastDb) {
        //Start at beginning (genesis)
        $this->ledger_current = config('xrpl.genesis_ledger');
        $start = ripple_epoch_to_epoch(config('xrpl.genesis_ledger_close_time'));
      } else {
        $this->ledger_current = $LastDb->ledger_index_last + 1;
        $start = $this->fetchLedgerIndexTime($this->ledger_current)->timestamp;
      }
      
      $period = CarbonPeriod::since(Carbon::createFromTimestamp($start))->days(1)->until(now());

      $bar = $this->output->createProgressBar($period->count());
      $bar->start();
      foreach($period as $day) {

        # find last ledger index for this $day
        $day_last_ledger_index = $this->findLastLedgerIndexForDay($day, $this->ledger_current, $this->ledger_last, $this->ledger_last);
        dump($day_last_ledger_index. ' - '. $day->format('Y-m-d'));
        $this->ledger_current = $day_last_ledger_index+1;
        //save to local db $day_last_ledger_index is last ledger of $day
        $this->saveToDb($day_last_ledger_index,$day);
        $bar->advance();
      }
      $bar->finish();
      
    }

    private function saveToDb(int $ledger_index, Carbon $day): void
    {
      $model = new Ledgerindex;
      $model->ledger_index_last = $ledger_index;
      $model->day = $day;
      $model->save();
    }


    private function findLastLedgerIndexForDay(Carbon $day, int $low, int $high, int $lastHigh): int
    {
      $day->endOfDay(); //set to end of day

      $time_high = $this->fetchLedgerIndexTime($high);
      if($time_high->greaterThan($day)) //too high
      {
        return $this->findLastLedgerIndexForDay($day, $low, $this->halveNumbers($low,$high), $high);
      }
      else
      {
        //$high ledger is somewhere between $low and end of $day
        //check if next ledger is in next day, if not then continue with adjusted ranges
        $next_ledger_time = $this->fetchLedgerIndexTime($high+1);
        if($next_ledger_time->greaterThanOrEqualTo($day)) //Found it.
          return $high;
        else //contine search with adjusted lower threshold...
          return $this->findLastLedgerIndexForDay($day, $high, $this->halveNumbers($high,$lastHigh),$lastHigh);
      }
    }

    private function halveNumbers($low,$high): int
    {
      $n = ($high+$low)/2;
      $n = ceil($n);
      dump( 'L: '.$low.' H: '. $high. ' N: '.(int)$n);
      return (int)$n;
    }

    private function fetchLedgerIndexTime(int $index): Carbon
    {
      $ledger_result = $this->fetchLedgerIndexInfo($index);
      return \Carbon\Carbon::createFromTimestamp(ripple_epoch_to_epoch($ledger_result->close_time));
    }

    private function fetchLedgerIndexInfo(int $index)
    {
      $ledger = $this->XRPLClient->api('ledger')
      ->params([
        'ledger_index' => $index,
        'accounts' => false,
        'full' => false,
        'transactions' => false,
        'expand' => false,
        'owner_funds' => false,
      ]);
     return  $ledger->send()->finalResult();
    }
}
