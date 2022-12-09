<?php

namespace App\Repository;

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

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne($where): ?array
  {
    $query = 'SELECT address,l,lt,activatedBy,isdeleted FROM `'.config('bigquery.project_id').'.xwa.accounts` WHERE '.$where.' LIMIT 1';
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

    $insert ='INSERT INTO `'.config('bigquery.project_id').'.xwa.accounts` ('.\implode(',',\array_keys($values)).') VALUES (';
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