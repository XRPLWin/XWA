<?php

namespace App\Repository;

use App\Models\B;

/**
 * Batch queuer for query list to be executed
 */
class Batch
{
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
   * @return void
   */
  public function execute(): void
  {
    if(!count($this->queue))
      return;

    //Group by table and method
    $q = [];
    foreach($this->queue as $data) {
      $q[$data['table']][$data['method']][] = ['fields' => $data['fields'], 'model' => $data['model']];
    }
    unset($data);
    # Execute
    foreach($q as $table => $methods) {
      foreach($methods as $method => $data) { //insert|update => []
        if($method === 'insert') {
          $executeResult = $this->executeInsert($table,$data);
          if(!$executeResult['success']) {
            throw new \Exception('Unsuccessful executeInsert with errors: '.\implode('; ',$executeResult['errors']));
          }
        }
        elseif($method == 'update') {
          $executeResult = $this->executeUpdate($table,$data);
          if(!$executeResult['success']) {
            throw new \Exception('Unsuccessful executeUpdate with errors: '.\implode('; ',$executeResult['errors']));
          }
        }
        else
          throw new \Exception('Unhadled save method ['.$method.']');
      }
    }
  }

  /**
   * If one fails then every row is rolled back (stopped )
   * @return array ['success' => BOOL, 'errors' => ARRAY ]
   */
  private function executeUpdate($table, $data)
  {
    $success = true;

    if($table == 'transactions') {
      foreach($data as $v) {
        unset($v['fields']['PK']);
        unset($v['fields']['SK']);
        $result = AccountsRepository::update($table, $v['model']->bqPrimaryKeyCondition(), $v);
        if($result === false) {
          $success = false;
          break;
        }
      }
    }
    elseif($table == 'accounts') {
      foreach($data as $v) {
        unset($v['fields']['address']);
        $result = TransactionsRepository::update($table, $v['model']->bqPrimaryKeyCondition(), $v);
        if($result === false) {
          $success = false;
          break;
        }
      }
    }
    else
      throw new \Exception('Not implemented repo for ['.$table.']');
    
    return ['success' => $success, 'errors' => []];
  }

  /**
   * If one fails then every row is rolled back (stopped )
   * @return array ['success' => BOOL, 'errors' => ARRAY ]
   */
  private function executeInsert($table, $data): array
  {
    $bq = app('bigquery');
    $table = $bq->dataset('xwa')->table($table);
    
    $id = 1;
    $rows = [];
    foreach($data as $v) {
      $rows[] = ['insertId' => $id, 'data' => $v['fields']];
      $id++;
    }

    //Testing invalid row:
    //$rows[] = ['insertId' => $id+1, 'data' => ['foo' => 'bar']];

    $success = true;
    $errors = [];
    //dd(collect($rows)->pluck('data.h')->toArray(),collect($rows)->pluck('data.PK')->toArray());
    $insertResponse = $table->insertRows($rows, ['retries' => 5]);
    if (!$insertResponse->isSuccessful()) {
      $success = false;
      foreach ($insertResponse->failedRows() as $row) {
        foreach ($row['errors'] as $error) {
          $errors[] = $error['reason'] . ': ' . $error['message'];
        }
      }
    }

    return ['success' => $success, 'errors' => $errors];
  }
}