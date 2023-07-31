<?php

namespace App\Repository;

use App\Models\BTransaction;

class TransactionsRepository extends Repository
{
  /**
   * Fetches one record from database.
   * @return ?\stdClass
   */
  public static function fetchOne(string $where, ?string $columns = null, string $orderBy = ''): ?\stdClass
  {
    $results = self::fetchMany($where, $columns, $orderBy, 1);
    $r = null;
    foreach ($results as $row) {
      $r = (object)$row;
      break;
    }
    return $r;
  }

  public static function fetchMany(string $where, ?string $columns = null, string $orderBy = '', int $limit): \Google\Cloud\BigQuery\QueryResults
  {
    if($columns === null)
      $columns = 'SK,PK,h,t,r,isin,fee,a,i,c,a2,i2,c2,dt,st';
    if($orderBy !== '')
      $orderBy = ' ORDER BY '.$orderBy;
    
    $query = 'SELECT '.$columns.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.transactions` WHERE '.$where.''.$orderBy.' LIMIT '.$limit;
    try {
      $r = \BigQuery::runQuery(\BigQuery::query($query));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      //return false;
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


    $insert ='INSERT INTO `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.transactions` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BTransaction::BQCASTS, $values);
    $insert .= \implode(',',$castedValues);
    $insert .= ')';

    try {
      \BigQuery::runQuery(\BigQuery::query($insert));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      return false;
    }
    return true;
  }


  
}