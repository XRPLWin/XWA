<?php

namespace App\Repository;

class Repository
{
  /**
   * Takes array of values and casts them using definition.
   * For building insert query.
   * @see https://cloud.google.com/bigquery/docs/reference/standard-sql/lexical
   * @return array
   */
  public static function valuesToCastedValues(array $definition, array $values, bool $escapeStrings = true): array
  {
    $r = [];
    foreach($values as $k => $v) {
      
      if(!$escapeStrings) {
        $r[$k] = $v;
        continue;
      }
        
      $cast = $definition[$k];
      switch ($cast) {
        //case 'BIGNUMERIC':
        //  $r[$k] = \BigQuery::bigNumeric($v);
        //  break;
        case 'NULLABLE INTEGER':
          $r[$k] = ($v === null) ? 'NULL':$v;
          break;
        case 'STRING':
          $r[$k] =  '"""'.$v.'"""'; //tripple quote escape
          break;
        case 'NULLABLE STRING':
          $r[$k] = ($v === null) ? 'NULL':'"""'.$v.'"""';
          break;
        case 'BOOLEAN':
          $r[$k] = $v?'true':'false';
          break;
        case 'TIMESTAMP':
          $r[$k] = '\''.$v.'\'';
          break;
        default:
          $r[$k] = $v;
      }
    }
    
    return $r;
  }

  public static function update(string $table, string $conditions, array $modelandfields): ?bool
  {
    if(empty($modelandfields['fields']))
      return null;

    $q ='UPDATE `'.config('bigquery.project_id').'.xwa.'.$table.'` SET';
    $i = 0;
    foreach($modelandfields['fields'] as $k => $v) {
      if($i > 0) $q .= ',';
      $q .= ' '.$k.' = '.$v;
      $i++;
    }
    $q .= ' WHERE '.$conditions;

    $query = \BigQuery::query($q);
    try {
      \BigQuery::runQuery($query);
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      return false;
    }
    return true;
  }

  /**
   * Executes query.
   */
  public static function query(string $q, array $options = []): ?\Google\Cloud\BigQuery\QueryResults
  {
    $results = null;

    $query =  \BigQuery::query($q);
    try {
      $results =  \BigQuery::runQuery($query);
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
    return $results;
  }

}