<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Utilities\AccountLoader;

class XwaDebug extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:debug {method : Thing to debug} {param : Param for method}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug various things.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $method = $this->argument('method');
        $param = $this->argument('param');

        if($method == 'getFirstTransactionAllInfo') {
            $acct = AccountLoader::get($param);
            $firstTxInfo = $acct->getFirstTransactionAllInfo();
            dd($acct,$firstTxInfo);
            return Command::SUCCESS;
        }
        
    }
}
