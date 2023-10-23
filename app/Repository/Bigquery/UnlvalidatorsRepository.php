<?php

namespace App\Repository\Bigquery;

use App\Models\BUnlvalidator;

class UnlvalidatorsRepository extends Repository
{
  
  public static function fetchAll(?string $select = null): array
  {
    if($select === null)
    $select = 'validator,account,first_l,last_l,current_successive_fl_count,max_successive_fl_count,active_fl_count';
    
      $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlvalidators`';
      try {
        $results = \BigQuery::runQuery(\BigQuery::query($query));
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
   * Load account data by validator key.
   * @return ?array
   */
  public static function fetchByValidator(string $validator, ?string $select = null): ?array
  {
    return self::fetchOne('WHERE validator = """'.$validator.'"""',$select);
  }

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne(string $where, ?string $select = null): ?array
  {
    if($select === null)
      $select = 'validator,account,first_l,last_l,current_successive_fl_count,max_successive_fl_count,active_fl_count';

    $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlvalidators` '.$where.' LIMIT 1';
    
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

    $insert ='INSERT INTO `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlvalidators` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BUnlvalidator::BQCASTS, $values);
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