<?php

namespace App\Repository;

use App\Models\BUnlreport;

class UnlreportsRepository extends Repository
{
  /**
   * Load account data by ledger_index.
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

  public static function fetchByLedgerIndexRange(int $start_li, int $end_li, ?string $select = null, string $where = '')
  {
    if($select === null)
      $select = 'first_l,last_l,vlkey,validators';

    $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlreports` WHERE ('.
             //'WHERE first_l BETWEEN '.$start_li.' AND '.$end_li.' ORDER BY first_l ASC';
             //'WHERE (first_l BETWEEN '.$start_li.' AND '.$end_li.') OR (last_l BETWEEN '.$start_li.' AND '.$end_li.') ORDER BY first_l ASC';
             //INNER:
             '(first_l between '.$start_li.' AND '.$end_li.' AND last_l between '.$start_li.' AND '.$end_li.')'.
             //BORDER RIGHT:
             ' OR (first_l between '.$start_li.' AND '.$end_li.' AND last_l >= '.$end_li.')'.
             //BORDER LEFT:
             ' OR (first_l <= '.$start_li.' AND last_l between '.$start_li.' AND '.$end_li.')'.
             //OUTER:
             ' OR (first_l <= '.$start_li.' AND last_l >= '.$end_li.')) '.$where;

    try {
      $results = \BigQuery::runQuery(\BigQuery::query(\trim($query)));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
    
    $r = [];
    foreach($results->rows(['returnRawResults' => false]) as $row) {
      $r[] = $row;
    }
    return $r;
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