<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SystemInitLocalDb extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:initlocaldb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates (if not created) empty sqlite database file storage/localdb/db.sqlite';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
      //Not used
      /*
      $disk = Storage::build([
          'driver' => 'local',
          'root' => base_path(),
      ]);

      if( !$disk->exists(config('database.connections.sqlite.database')) )
      {
        $disk->put(config('database.connections.sqlite.database'), '');
        $this->info('Initial empty SQLite database created ('.config('database.connections.sqlite.database').')');
      }
      else
        $this->info('Local SQLite database file exists, nothing to do ('.config('database.connections.sqlite.database').')');
      */
      return 0;
    }
}
