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

    /**
     * The number of seconds the job can run before timing out.
     * 30 min to execute something, if timeouts job can be restarted
     * (please program jobs to continue where they left of)
     * @var int
     */
    public $timeout = 600;  //10 min per job max

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
