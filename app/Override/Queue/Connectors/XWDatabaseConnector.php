<?php

namespace App\Override\Queue\Connectors;

use App\Override\Queue\XWDatabaseQueue;
use Illuminate\Queue\Connectors\DatabaseConnector;

class XWDatabaseConnector extends DatabaseConnector
{
    /**
     * Establish a queue connection.
     *
     * @param  array  $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        return new XWDatabaseQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60
        );
    }
}
