<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class XwaGenerateQueueSupervisorconf extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xwa:generatequeuesupervisorconf';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates Supervisor configuration and saves to local .ini file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $str = '';

        $groups = config('xwa.queue_groups');
        foreach($groups as $name => $v) {
            $str .= '
[program:queue-worker-'.$name.']
process_name=%(program_name)s_%(process_num)02d
command=php /opt/nginx/htdocs/xwa/artisan queue:work --queue='.$name.' --sleep=5 --tries=1
autostart=true
autorestart=true
user=daemon
numprocs=1
redirect_stderr=true
stdout_logfile=var/log/supervisor/xwa-queue-worker-'.$name.'.log
';
        }
        Storage::disk('private')->put('queue.ini', $str);
        $this->info('Configuration file stored to '.Storage::disk('private')->path('queue.ini'));
        $this->info('Use this configuration file to run supervisor queue workers.');
        return Command::SUCCESS;
    }
}
