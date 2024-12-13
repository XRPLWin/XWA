<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Nftfeed;

class XwaCleanuphourly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:cleanuphourly';

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
        $this->cleanupNftfeeds();

        return Command::SUCCESS;
    }

    /**
     * Remmove old rows from nftfeeds table.
     */
    private function cleanupNftfeeds()
    {
        $this->info('Running cleanupNftfeeds');
        //$deleteOlderThanMinutes = 360; //360 - 6 hours history we keep
        $deleteOlderThanMinutes = config('xwa.nftfeed_max_days') * 24 * 60; //2 days
        $t = now()->addMinutes(-$deleteOlderThanMinutes);
        $t = $t->format('Y-m-d H:i:s.uP');
        /*$check = Nftfeed::select('t')
            ->where('t','<=',$t)
            //->where('test',123)
            ->get();
        dd($check->toArray());*/
        Nftfeed::where('t','<=',$t)->limit(1000)->delete();
    }
}
