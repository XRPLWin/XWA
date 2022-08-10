<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
#use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class QueueArtisanCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $command;
    protected $command_options;

    //Additional data for queue table
    public $qtype = '';       //string max 50
    public $qtype_data = '';  //string max 255

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $command, array $command_options, string $qtype, string $qtype_data)
    {
        $this->command = $command;
        $this->command_options = $command_options;
        $this->qtype = $qtype;
        $this->qtype_data = $qtype_data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
      return Artisan::call($this->command, $this->command_options);
    }
}
