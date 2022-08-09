<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;
use XRPLWin\XRPL\Client;

class XRPLServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Client::class, function ($app) {
            $xrplnet = $app['config']['xrpl']['net'];
            $xrpl_config = $app['config']['xrpl'][$xrplnet];
            $config = [
                'endpoint_reporting_uri' => $xrpl_config['rippled_server_uri'],
                'endpoint_fullhistory_uri' => $xrpl_config['rippled_fullhistory_server_uri']
            ];

            return new Client($config);
        });
    }
    
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [Client::class];
    }
}
