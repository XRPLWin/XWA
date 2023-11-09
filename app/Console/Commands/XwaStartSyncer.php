<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
#use Symfony\Component\Process\Exception\ProcessFailedException;
#use Symfony\Component\Process\ExecutableFinder;
use App\Models\Synctracker;
use Illuminate\Support\Facades\DB;
#use Illuminate\Support\Facades\Log;

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

  protected int $proc_timeout = 600; //600 - must be same as in XwaContinuousSyncProc

  protected int $numberOfProcess = 8; //16
  protected int $ledgersPerProcess = 10; //1000
  
  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle_old()
  {


  }

  /**
   * Execute the console command.
   *
   * @return int
   */
  public function handle_old()
  {
    //$this->normalizeSynctrackers();return;
    /*$executableFinder = new ExecutableFinder();
    $phpPath = $executableFinder->find('php');
    dd($chromedriverPath);
    exit;*/

    $this->cleanupStuckTrackers();

    $numberOfProcess = 8; //16
    $ledgersPerProcess = 10; //1000

    $emulate = (int)$this->option('emulate'); //int
    $first_l = (int)config('xrpl.'.config('xrpl.net').'.genesis_ledger'); //starting ledger
    $firstTwoTrackers = Synctracker::select('first_l','progress_l','last_l','is_completed')
      ->orderBy('first_l','ASC')
      ->limit(2)
      ->get();

    if($firstTwoTrackers->count() > 1) {
      
      if($firstTwoTrackers->first()->isCompleted() && $firstTwoTrackers->last()->isCompleted()) {
        
        if($firstTwoTrackers->first()->last_l+1 == $firstTwoTrackers->last()->first_l) {
          dd('Trackers not merged correctly!');
        } else {
          $this->info('Catching up missed tracker with single job...');

          //there might be missed trackers before this one
          /*if($firstTwoTrackers->first()->first_l > $first_l) {
            //start from genesis
            $numberOfProcess = 1;
          } else {
            $first_l = $firstTwoTrackers->first()->last_l+1;
          }*/

          $first_l = $firstTwoTrackers->first()->last_l+1;
          $numberOfProcess = 1;
          $wantLedgers = $firstTwoTrackers->last()->first_l - $first_l;
          if($ledgersPerProcess >= $wantLedgers)
            $ledgersPerProcess = $wantLedgers;
          
          //dd($first_l,$numberOfProcess,$ledgersPerProcess );
        }
      } else {
        $this->info('Trackers are in progress, continuing...');
        $last = Synctracker::select('last_l')->orderBy('first_l','desc')->first();
        $first_l = $last->last_l+1;
        //dd($last,$first_l);
      }
    } elseif ($firstTwoTrackers->count() == 1) {
      

      //there might be missed trackers before this one
      if($firstTwoTrackers->first()->first_l > $first_l) {
        //start from genesis
        $numberOfProcess = 1;
      } else {
        $first_l = $firstTwoTrackers->first()->last_l+1;
      }
      
    } else {
      //No trackers yet
    }
    //exit;
    //if($tracker) {
    //  $first_l = $tracker->last_l+1;
    //}

    if($emulate) {
      $this->info('Emaulate enabled, no jobs will be started');
      for ($i = 0; $i < $numberOfProcess; $i++) {
        $start_l = $first_l+($i*$ledgersPerProcess);
      $end_l = $start_l+$ledgersPerProcess-1;
      $this->info('Emulating '.$i. ': php artisan xwa:continuoussyncproc '.$start_l.' '.$end_l);
      }
      $this->info('Exiting');
      return;
    }
    $processes = [];
    for ($i = 0; $i < $numberOfProcess; $i++) {
      $start_l = $first_l+($i*$ledgersPerProcess);
      $end_l = $start_l+$ledgersPerProcess-1;
      $this->info('Starting '.$i. ': php artisan xwa:continuoussyncproc '.$start_l.' '.$end_l);
      $process = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc',(string)$start_l,(string)$end_l]);
      $process->setTimeout($this->proc_timeout); //10 mins max run
      //$process->disableOutput();
      
      $process->start();
      $processes[] = ['proc' => $process,'start_l' => $start_l, 'end_l' => $end_l];
      sleep(2);
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
            //remove failed tracker
            /*DB::table('synctrackers')
              ->where('first_l',$v['start_l'])
              ->where('last_l',$v['end_l'])
              ->delete();*/
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

    
    //TODO

    $this->info('Shutting down manager');
    return Command::SUCCESS;



    /*
    $process = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc','73807163','73807173']);
    $process2 = new Process(['php', base_path('artisan'),'xwa:continuoussyncproc','73808163','73808173']);
    //$process->disableOutput();
    $process->setTimeout(0);
    $process2->setTimeout(0);
    $process->start();
    sleep(5);
    $this->info('killed');
    return;
    //dd($process);
    foreach ($process as $type => $data) {
        if ($process::OUT === $type) {
            echo "\nRead from stdout: ".$data;
        } else { // $process::ERR === $type
            echo "\nRead from stderr: ".$data;
        }
    }

    return Command::SUCCESS;*/
  }

  private function cleanupStuckTrackers(): void
  {
    //delete stuck trackers (updated_at > proc_timeout + 300 seconds)

    //TODO
  }

  /**
   * Merges consecutive trackers into one.
   */
  private function normalizeSynctrackers(): void
  {
    DB::beginTransaction();
    $all = Synctracker::orderBy('first_l','ASC')->limit(500)->get();
    
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
            $prev->progress_l = $t->progress_l;
            $prev->last_l = $t->last_l;
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
  }
}
