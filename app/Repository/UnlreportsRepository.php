<?php

namespace App\Repository;

use App\Models\BUnlreport;

class UnlreportsRepository extends Repository
{
  /**
   * Load account data by address.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByLedgerIndex(int $ledger_index, ?string $select = null): ?array
  {
    return self::fetchOne('WHERE first_l <= '.$ledger_index.' AND last_l >= '.$ledger_index.' ORDER BY first_l ASC',$select);
  }

  public static function fetchLastRow(?string $select = null)
  {
    return self::fetchOne('ORDER BY first_l DESC',$select);
  }

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne(string $where, ?string $select = null): ?array
  {
    if($select === null)
      $select = 'first_l,last_l,vlkey,validators';

    $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlreports` '.$where.' LIMIT 1';

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

    $insert ='INSERT INTO `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlreports` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BUnlreport::BQCASTS, $values);
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