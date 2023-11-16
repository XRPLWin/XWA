<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class Banner extends Command
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
    protected $description = 'Cleans up logs';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        //Todo remove logs
        return Command::SUCCESS;
    }
}
