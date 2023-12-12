<?php

namespace App\Console\Commands;

#use App\Models\B;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
#use Symfony\Component\Process\Exception\ProcessFailedException;
#use Symfony\Component\Process\ExecutableFinder;
use App\Models\Synctracker;
use Illuminate\Support\Facades\DB;
#use Illuminate\Support\Facades\Log;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;

class XwaStartSyncer extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'xwa:startsyncer {--emulate=0}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'This will start continous syncer threads.';

  protected int $proc_timeout = 600; //600s  - must be same as in XwaContinuousSyncProc

  protected int $numberOfProcess = 1; //16 - overriden from config
  protected int $ledgersPerProcess = 1000; //1000
  
  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle()
  {
    //$this->normalizeSynctrackers();return;
    //Check if is enabled
    if(config('xwa.sync_type') != 'continuous') {
      $this->info('Continous sync is not enabled');
      return Command::SUCCESS;
    }
    
    //Check if already running
    $check = Cache::get('job_xwastartsyncer_sync_running');
    if($check !== null) {
      $this->info('Another instance already active, exiting.');
      return Command::SUCCESS;
    }

    $this->numberOfProcess = (int)config('xwa.sync_type_continuous.processes');

    $emulate = (int)$this->option('emulate'); //int
    
    if($emulate) {
      $this->info('Emulate enabled, no jobs will be started.');
    } else {
      Cache::put('job_xwastartsyncer_sync_running', true, ($this->proc_timeout+60));
    }

    $plan = $this->threadPlan();
    
    $processes = [];
    $i = 0;
    foreach($plan as $ranges) {
      $i++;
      $this->line('['.$i.'/'.$this->numberOfProcess.'] '.($emulate ? 'Emulating':'Starting').': php artisan xwa:continuoussyncproc '.$ranges[0].' '.$ranges[1]);
      if(!$emulate) {
        $process = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc',(string)$ranges[0],(string)$ranges[1]]);
        $process->setTimeout($this->proc_timeout); //10 mins max run
        //$process->disableOutput();
        $process->start();
        $processes[] = ['proc' => $process,'start_l' => $ranges[0], 'end_l' => $ranges[1]];
        sleep(2);
      }
    }

    if($emulate) {
      $this->info('Exiting');
      return Command::SUCCESS;
    }

    // wait for above processes to complete
    while (count($processes)) {
      foreach ($processes as $i => $v) {
        // specific process is finished, so we remove it   
        if (!$v['proc']->isRunning()) {
          
          if(!$v['proc']->isSuccessful()){
            //dd($v['proc']->getErrorOutput(),$v['proc']->getIncrementalOutput(),$v['proc']->getIncrementalErrorOutput());
            echo $v['proc']->getIncrementalOutput();
            $this->error('Process '.$i.' failed, tracker removed (error outputed above and logged in job error log).');
            //$exception = new ProcessFailedException($v['proc']);
            //dump( $exception->getTraceAsString() );
            //Log::error($exception->getTraceAsString());
          } else {
            $this->info('Process '.$i.' completed'); 
          }
          unset($processes[$i]);    
        } else {
          //$this->info('Process '.$i.' running...'); 
        } 
        sleep(1);
      }
    }
    $this->info('Normalizing sync trackers...');
    $this->normalizeSynctrackers();
    sleep(3);
    Cache::delete('job_xwastartsyncer_sync_running');
    $this->info('Shutting down manager');
    return Command::SUCCESS;
  }

  private function threadPlan(): array
  {
    $plan = [];
    $absMin = (int)config('xrpl.'.config('xrpl.net').'.genesis_ledger');
    $absMax = Ledger::validated();
    $min = $absMin;
    //Generate x number of processes
    for ($i = 0; $i < $this->numberOfProcess; $i++) {
      
      $threadPlanItem = $this->threadPlanItem($min);
      $min = $threadPlanItem[0];
      //dump($threadPlanItem);

      //Do process with starting as $min to $min + 10
      //$this->info('['.$i.'] php artisan xwa:continuoussyncproc '.$min.' '.($min+$this->ledgersPerProcess).' max: '.$absMax);
      if($min < $absMax) {
        //start range allowed

        if(($min+$this->ledgersPerProcess-1) < $absMax) {
          //end range allowed
          
          if($threadPlanItem[1] !== null)
            $plan['k'.$min] = [$min,$threadPlanItem[1]];
          else
            $plan['k'.$min] = [$min,($min+$this->ledgersPerProcess-1)];
          $min += $this->ledgersPerProcess;
        } else {
          //end range not allowed (max ledger reached)
          if($threadPlanItem[1] !== null)
            $plan['k'.$min] = [$min,$threadPlanItem[1]];
          else
            $plan['k'.$min] = [$min,$absMax];
          break;
        }
        
      }

      if($threadPlanItem[1] !== null)
        $min = $threadPlanItem[1]+1;

    }
    return $plan;
  }

  private function threadPlanItem(int $min): array
  {
    $max = null;
    $synctracker = Synctracker::where('first_l',$min)->orderBy('first_l','asc')->first();
    
    if($synctracker) {
      $max = $synctracker->last_l;
      //Tracker already found
      return $synctracker->isCompleted() ? 
        $this->threadPlanItem($synctracker->last_l+1) : [$min,$max,'incomplete'];
    } else {
      //Tracker not found with exact range,
      // check if there is missing hole
      $synctracker = Synctracker::where('first_l','>',$min)->orderBy('first_l','asc')->first();
      if($synctracker) {
        if(($synctracker->first_l-1 - $min) > $this->ledgersPerProcess) {
          return [$min,$min+$this->ledgersPerProcess,'missing'];
        } else {
          return [$min,$synctracker->first_l-1,'missing'];
        }
      }
      
    }
    
    //Tracker not found
    return [$min,$max,'end'];
  }

  /**
   * Merges consecutive trackers into one.
   */
  private function normalizeSynctrackers(): void
  {
    //return;
    DB::beginTransaction();
    $all = Synctracker::select([
        'id','first_l','last_synced_l',
        'last_l','last_lt','is_completed',
        'created_at','updated_at'
      ])
      ->orderBy('first_l','ASC')->limit(500)->get();
    
    $prev = null;
    foreach($all as $t) {
      
      if($prev === null) {
        if($t->isCompleted()) {
          $prev = $t;
          //$this->info('initial prev is '.$t->id);
        }
        continue;
      }
      if($t->isCompleted()) {
        
        //Check if prev and $t can be merged
        if($t->isCompleted() && $prev->isCompleted()) {
          
          if(($prev->last_l+1) == $t->first_l) {
            //merge them
            $prev->last_synced_l = $t->last_synced_l;
            $prev->last_l = $t->last_l;
            if($t->last_lt !== null) $prev->last_lt = $t->last_lt;
            $prev->save();
            //$this->info('save '.$prev->id);
            $t->delete();
            //$this->info('delete '.$t->id);
          } else {
            //$this->info('set prev to '.$t->id);
            $prev = $t;
          }
        }
      } else {
        $prev = null;
        //$this->info('set prev to null');
      }
    }
    DB::commit();
    Cache::delete('lastcompletedsyncedledgerdata');
  }
}
