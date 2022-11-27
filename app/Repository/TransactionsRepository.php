<?php

namespace App\Repository;

use App\Models\BTransaction;

class TransactionsRepository extends Repository
{
  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne($where): ?array
  {
    $bq = app('bigquery');
    $query = 'SELECT address,l FROM `'.config('bigquery.project_id').'.xwa.transactions` WHERE '.$where.' LIMIT 1';
    $results = $bq->runQuery($bq->query($query));
    $r = null;
    foreach ($results as $row) {
      $r = $row;
      break;
    }
    return $r;
  }

  /**
   * Inserts one record to database.
   * @return bool true on success
   */
  public static function insert(array $values): bool
  {
    if(!count($values))
      throw new \Exception('Values missing');


    $insert ='INSERT INTO `'.config('bigquery.project_id').'.xwa.transactions` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BTransaction::BQCASTS, $values);
    $insert .= \implode(',',$castedValues);
    $insert .= ')';
    $bq = app('bigquery');
    $dataset = $bq->dataset('xwa');
    $query = $bq->query($insert)->defaultDataset($dataset);
    try {
      $bq->runQuery($query);
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      return false;
    }
    return true;
  }

  
}