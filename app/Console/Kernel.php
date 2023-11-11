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
    $schedule->command('xwa:downloadtokendata')
        ->withoutOverlapping(10) //lock expires every 10 mins
        ->daily()
        ->onOneServer()
        //->sendOutputTo(storage_path('logs/nftsync.log')) //logging
        ;
    $schedule->command('xwa:unlreportssync')
      ->withoutOverlapping(4) //lock expires every 4 mins, flag ledgers are approx every 12 mins
      ->everyFiveMinutes()
      ->onOneServer(); 

    $schedule->command('xwa:startsyncer')
      ->withoutOverlapping(1) //lock expires every 1 min
      ->everyThreeMinutes()
      ->everyMinute();  
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
