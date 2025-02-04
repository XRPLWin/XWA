<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
  /**
   * Define the application's command schedule.
   *
   * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
   * @return void
   */
  protected function schedule(Schedule $schedule)
  {
    $schedule->command('cache:prune-stale-tags')->hourly();
    
    $schedule->command('xwa:downloadtokendata')
        ->withoutOverlapping(10) //lock expires every 10 mins
        ->daily()
        ->onOneServer()
        //->sendOutputTo(storage_path('logs/nftsync.log')) //logging
        ;
    $schedule->command('xwa:unlreportssync')
      ->withoutOverlapping(4) //lock expires every 4 mins, flag ledgers are approx every 12 mins
      ->everyFiveMinutes()
      ->onOneServer()
      ->runInBackground();

    $schedule->command('xwa:startsyncer')
      ->withoutOverlapping(11) //lock expires every 21 min (adjust times in commands also)
      ->everyThirtySeconds()
      ->onOneServer()
      ->runInBackground();

    $schedule->command('xwa:startaggrsyncer')
      ->withoutOverlapping(11) //lock expires every 21 min (adjust times in commands also)
      ->everyThirtySeconds()
      ->onOneServer()
      ->runInBackground();

    /*$schedule->command('xwa:aggrhooktransactions')
      ->withoutOverlapping(10) //lock expires every 10 mins
      ->everyFiveMinutes()
      ->onOneServer()
      ->runInBackground();*/

    $schedule->command('xwa:aggramm')
      ->withoutOverlapping(35) //lock expires every 35 mins
      ->everyThirtyMinutes()
      ->onOneServer()
      ->runInBackground();

    $schedule->command('xwa:cleanupdaily')
      ->withoutOverlapping(10) //lock expires every 10 mins
      ->daily()
      ->onOneServer();

    $schedule->command('xwa:cleanuphourly')
      ->withoutOverlapping(10) //lock expires every 10 mins
      ->hourly()
      ->onOneServer();  
  }

  /**
   * Register the commands for the application.
   *
   * @return void
   */
  protected function commands()
  {
    $this->load(__DIR__.'/Commands');

    require base_path('routes/console.php');
  }
}
