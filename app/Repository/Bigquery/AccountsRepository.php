<?php

namespace App\Repository\Bigquery;

use App\Models\BAccount;

class AccountsRepository extends Repository
{
  /**
   * Load account data by address.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByAddress(string $address): ?array
  {
    return self::fetchOne('address = """'.$address.'"""');
  }

  public static function getFirstTransactionAllInfo(): array
  {
    $bigqueryresults = self::query(
      'SELECT xwatype,t FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.transactions` WHERE TRUE QUALIFY ROW_NUMBER() OVER (PARTITION BY xwatype ORDER BY t ASC) = 1'
    );

    $collection = [];
    foreach($bigqueryresults as $row) {
      $collection[$row['xwatype']] = (int)$row['t']->get()->format('U');
    }
    return $collection;
  }

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne($where): ?array
  {
    $query = 'SELECT address,l,li,lt,activatedBy,isdeleted FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.accounts` WHERE '.$where.' LIMIT 1';
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
  public static function insert(array $values): bool
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
  }
}