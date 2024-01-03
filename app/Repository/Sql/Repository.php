<?php

namespace App\Repository\Sql;

use App\Repository\Base\RepositoryInterface;

abstract class Repository implements RepositoryInterface
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
        case 'ARRAY':
          if(count($v) > 0)
            $r[$k] = '["""'.\implode('""","""',$v).'"""]';
          else
            $r[$k] = 'NULL';
          break;
        case 'RECORD':
          if(count($v)) {
            $parsed_records = [];
            foreach($v as $record_k => $record) {
              $parsed_records[$record_k] = [];
              foreach($record as $record_field => $record_fieldvalue) {
                //dd($k,$record_field);
                $_cast = $definition[$k.'.'.$record_field];
                if($_cast == 'STRING') {
                  $parsed_records[$record_k][$record_field] = '"""'.$record_fieldvalue.'"""';
                } else {
                  throw new \Exception('Unhandled record cast "'.$_cast.'"');
                }
              }
              $parsed_records[$record_k] = '('.\implode(',',array_values($parsed_records[$record_k])).')';
              
            }
            $r[$k] = '['.\implode(',',\array_values($parsed_records)).']';
            //dd($r);
            //$r[$k] = '('.\implode(',',\array_values($v)).')'; //see https://www.adaltas.com/en/2019/11/22/bigquery-insert-complex-column/
          }
          else
            $r[$k] = 'NULL';
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

    $q ='UPDATE `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.'.$table.'` SET';
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
   * @deprecated
   */
  /*public static function query(string $q, array $options = []): ?\Google\Cloud\BigQuery\QueryResults
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
  }*/

  

}