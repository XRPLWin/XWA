<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class XwaContinuousSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:continuoussync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync transactions in ledgers, one by one.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return Command::SUCCESS;
    }
}
