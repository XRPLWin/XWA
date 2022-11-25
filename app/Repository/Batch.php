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
    //dd($changes);
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

    //Group by table
    $q = [];
    foreach($this->queue as $data) {
      $q[$data['table']][] = $data['fields'];
    }
    unset($data);

    $rows = [];
    //Create query for each table
    foreach($q as $table => $data) {
      $list[] = $this->executeInsert($table,$data);
    }
    dd($list);

    # Execute
  }

  private function executeInsert($table, $data): string
  {
    $bq = app('bigquery');
    $table = $bq->dataset('xwa')->table($table);
    
    $id = 1;
    $rows = [];
    foreach($data as $v) {
      $rows[] = ['insertId' => $id, 'data' => $v];
      $id++;
    }
    dump('flag');
    $insertResponse = $table->insertRows($rows, ['retries' => 5]);
    if (!$insertResponse->isSuccessful()) {
      foreach ($insertResponse->failedRows() as $row) {
        print_r($row['rowData']);

        foreach ($row['errors'] as $error) {
          echo $error['reason'] . ': ' . $error['message'] . PHP_EOL;
        }
      }
    }
    dd('done');
    dd($rows);
    //$table->insertRows([])
    dd($table);


    //$query = 'INSERT INTO '
    //dd('test');
    //$r = 'MERGE INTO `'.config('bigquery.project_id').'.xwa.'.$table.'` as T USING ';
  }
}