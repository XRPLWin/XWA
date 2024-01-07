<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class XwaCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up logs database and other, runs once per day';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //Todo remove logs

        //Todo remove old data from recent_aggrs table

        //Todo remove old .json cache of account information from disk
        
        return Command::SUCCESS;
    }
}
