<?php

namespace App\Repository\Bigquery;

use App\Models\BAccount;

class HooksRepository extends Repository
{
  /**
   * Load account data by address.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByHookHashAndLedgerFrom(string $hookhash, int $l_from, bool $lockforupdate = false): ?array
  {
    return self::fetchOne('hook = """'.$hookhash.'""" and l_from = '.$l_from);
  }

  public static function fetchByHookHash(string $hookhash): array
  {
    return self::fetchMany('hook = """'.$hookhash.'""" ORDER BY l_from desc');
  }

  /**
   * Fetches many records from database.
   * @return ?array
   */
  public static function fetchMany($where): ?array
  {
    $query = 'SELECT hook,txid,l_from,l_to,params,title,descr FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.hooks` WHERE '.$where;
    try {
      $results = \BigQuery::runQuery(\BigQuery::query($query));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
    $r = [];
    foreach($results as $row) {
      $r[] = $row;
    }
    return $r;
  }

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne($where): ?array
  {
    $query = 'SELECT hook,txid,l_from,l_to,params,title,descr FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.hooks` WHERE '.$where.' LIMIT 1';
    try {
      $results = \BigQuery::runQuery(\BigQuery::query($query));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
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
  /*public static function insert(array $values): bool
  {
    if(!count($values))
      throw new \Exception('Values missing');

    $insert ='INSERT INTO `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.accounts` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BAccount::BQCASTS, $values);
    $insert .= \implode(',',$castedValues);
    $insert .= ')';
    //dd($insert,$values);
    try {
      \BigQuery::runQuery(\BigQuery::query($insert));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      return false;
    }
    return true;
  }*/
}