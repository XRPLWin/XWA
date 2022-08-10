<?php

namespace App\Override\Queue;

use Illuminate\Queue\DatabaseQueue;
use Throwable;
use Illuminate\Support\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class XWDatabaseQueue extends DatabaseQueue
{
  /**
   * Create an array to insert for the given job.
   *
   * @param  string|null  $queue
   * @param  string  $payload
   * @param  int  $availableAt
   * @param  int  $attempts
   * @return array
   */
  protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0)
  {
    $qtype = '';
    $qtype_data = '';

    $jsonpayload = json_decode($payload);
    if(isset($jsonpayload->data->command)) {
      $model = \unserialize($jsonpayload->data->command);
      if(isset($model->qtype) && isset($model->qtype_data)) {
        $qtype = $model->qtype;
        $qtype_data = $model->qtype_data;
      }
    }

    return [
      'queue' => $queue,
      'attempts' => $attempts,
      'reserved_at' => null,
      'available_at' => $availableAt,
      'created_at' => $this->currentTime(),
      'payload' => $payload,
      'qtype' => $qtype,
      'qtype_data' => $qtype_data
    ];
  }
}
