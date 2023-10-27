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
    protected $signature = 'banner';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->line('╔═════════════════════════════════════════════════════════╗');
        $this->line('║ ██╗  ██╗██████╗ ██████╗ ██╗     ██╗    ██╗██╗███╗   ██╗ ║');
        $this->line('║ ╚██╗██╔╝██╔══██╗██╔══██╗██║     ██║    ██║██║████╗  ██║ ║');
        $this->line('║  ╚███╔╝ ██████╔╝██████╔╝██║     ██║ █╗ ██║██║██╔██╗ ██║ ║');
        $this->line('║  ██╔██╗ ██╔══██╗██╔═══╝ ██║     ██║███╗██║██║██║╚██╗██║ ║');
        $this->line('║ ██╔╝ ██╗██║  ██║██║     ███████╗╚███╔███╔╝██║██║ ╚████║ ║');
        $this->line('║ ╚═╝  ╚═╝╚═╝  ╚═╝╚═╝     ╚══════╝ ╚══╝╚══╝ ╚═╝╚═╝  ╚═══╝ ║');
        $this->line('╠═════════════════════════════════════════════════════════╣');
        $this->line('║                     All systems go!                     ║');
        $this->line('╚═════════════════════════════════════════════════════════╝');
        return Command::SUCCESS;
    }
}
