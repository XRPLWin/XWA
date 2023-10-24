<?php

namespace App\Repository\Bigquery;

use App\Repository\Base\BatchInterface;
use App\Models\B;

/**
 * Batch queuer for query list to be executed
 */
final class Batch implements BatchInterface
{
  /** List of models in queue to save (update or insert) */
  private array $queue = [];

  /**
   * Extracts fields that are planned to be changed or inserted as new from $model
   * Changes are added to queue to be executed at once.
   * @return void
   */
  public function queueModelChanges(B $model): void
  {
    $changes = $model->extractPreparedDatabaseChanges();
    $this->queue[] = $changes;
  }

  /**
   * Execute all queued changes and commit them to BigQuery database using MERGE statement.
   * @see https://cloud.google.com/bigquery/docs/reference/standard-sql/dml-syntax#merge_statement
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/blob/master/bigquery/api/src/import_from_storage_json.php
   * @see https://github.com/googleapis/google-cloud-php-bigquery/blob/main/src/Table.php#L654
   * @throws \Exception
   * @return int num processed rows
   */
  public function execute(): int
  {
    if(!count($this->queue))
      return 0;

    //Group by table and method
    $q = [];
    foreach($this->queue as $data) {
      $q[$data['table']][$data['method']][] = ['fields' => $data['fields'], 'model' => $data['model']];
    }
    unset($data);
    # Execute
    $processed_rows = 0;
    foreach($q as $table => $methods) {
      foreach($methods as $method => $data) { //insert|update => []
        if($method === 'insert') {
          $executeResult = $this->executeInsert($table,$data);
          if(!$executeResult['success']) {
            throw new \Exception('Unsuccessful executeInsert with errors: '.\implode('; ',$executeResult['errors']));
          }
          $processed_rows += $executeResult['processed_rows'];
        }
        elseif($method == 'update') {
          $executeResult = $this->executeUpdate($data);
          if(!$executeResult['success']) {
            throw new \Exception('Unsuccessful executeUpdate with errors: '.\implode('; ',$executeResult['errors']));
          }
          $processed_rows += $executeResult['processed_rows'];
        }
        else
          throw new \Exception('Unhadled save method ['.$method.']');
      }
    }

    return $processed_rows;
  }

  /**
   * If one fails then every row is rolled back (stopped )
   * @return array ['success' => BOOL, 'errors' => ARRAY ]
   */
  private function executeUpdate(array $data)
  {
    $success = true;
    foreach($data as $v) {
      if(!$v['model']->save())
        $success = false;
    }
    return ['success' => $success, 'errors' => [], 'processed_rows' => $success ? 1:0]; //todo collect errors
  }

  /**
   * If one fails then every row is rolled back (stopped )
   * @return array ['success' => BOOL, 'errors' => ARRAY ]
   */
  private function executeInsert($table, $data): array
  {
    $table = \BigQuery::dataset(config('bigquery.xwa_dataset'))->table($table);
    $processed_rows = 0;
    $rows = [];
    foreach($data as $v) {
      $rows[] = ['insertId' => $v['model']->address.'-'.$v['fields']['h'].'-'.$v['model']::TYPE, 'data' => $v['fields']];
      //unset($v['model']);
      $processed_rows++;
    }
    //dump($rows);
    //dd($rows);
    //Testing invalid row:
    //$rows[] = ['insertId' => $id+1, 'data' => ['foo' => 'bar']];

    $success = true;
    $errors = [];
    //dd(collect($rows)->pluck('data.h')->toArray(),collect($rows)->pluck('data.PK')->toArray());
    $insertResponse = $table->insertRows($rows, ['retries' => 5]);
    if (!$insertResponse->isSuccessful()) {
      $processed_rows = 0;
      $success = false;
      foreach ($insertResponse->failedRows() as $row) {
        foreach ($row['errors'] as $error) {
          $errors[] = $error['reason'] . ': ' . $error['message'];
        }
      }
    }

    //All models inserted, execute model events.
    foreach($data as $v) {
      $v['model']->applyInsertedEvent();
    }
    //dd($insertResponse);
    return ['success' => $success, 'errors' => $errors, 'processed_rows' => $processed_rows];
  }
}