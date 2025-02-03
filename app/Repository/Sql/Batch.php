<?php

namespace App\Repository\Sql;

use App\Repository\Base\BatchInterface;
use App\Models\B;
use Illuminate\Support\Facades\DB;

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
    //$changes = $model->extractPreparedDatabaseChanges();
    $this->queue[] = $model;
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

    $processed_rows = 0;
    DB::beginTransaction();
    foreach($this->queue as $m) {
      $isTransactionModel = $m instanceof \App\Models\BTransaction;
      if($isTransactionModel) {
        //use replace into instead of insert (manually delete and recreate row)
        try {
          $m->save();
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
          //$m->exists = true;
          //$m->delete(); //not working due to primary key misconfiguration in BTransaction parent model

          //remove duplicate manually:
          DB::connection($m->getConnectionName())->table($m->getTable())
            ->where('address',$m->address)
            ->where('l',$m->l)
            ->where('li',$m->li)
            ->where('xwatype',$m->xwatype)
            ->where('r',$m->r)
            ->delete();

          //re-save
          $m->save();
        }
      } else {
        $m->save();
      }
      $processed_rows++;
    }
    DB::commit();

    return $processed_rows;
  }
}