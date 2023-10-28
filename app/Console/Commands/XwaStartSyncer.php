<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class XwaStartSyncer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:startsyncer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This will start continous syncer threads.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $process = new Process(['php ' . base_path('artisan') . ' xwa:continuoussyncproc','73807163 73807173']);
        //$process = new Process(['C:/xampp824/php/php.exe ' . base_path('artisan') . ' xwa:continuoussyncproc 73807163 73807173']);
        
        $process->setTimeout(0);
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                echo "\nRead from stdout: ".$data;
            } else { // $process::ERR === $type
                echo "\nRead from stderr: ".$data;
            }
        }

        return Command::SUCCESS;
    }
}
