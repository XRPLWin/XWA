<?php

namespace App\Override\Queue;

use App\Override\Queue\Connectors\XWDatabaseConnector;
use Illuminate\Queue\QueueServiceProvider;
//https://medium.com/@nutandevjoshi/extend-override-laravel-queues-b3e136c1d42f
class XWQueueServiceProvider extends QueueServiceProvider
{

  /**
   * Register the database queue connector.
   *
   * @param  \Illuminate\Queue\QueueManager  $manager
   * @return void
   */
  protected function registerDatabaseConnector($manager)
  {
      $manager->addConnector('database', function () {
          return new XWDatabaseConnector($this->app['db']);
      });
  }

}
