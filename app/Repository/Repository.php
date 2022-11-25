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
      $cast = $definition[$k];

      switch ($cast) {
        case 'BIGNUMERIC':
          $r[$k] = \BigQuery::bigNumeric($v);
          break;
        case 'STRING':
          if($escapeStrings)
            $r[$k] =  '"""'.$v.'"""'; //tripple quote escape
          else
            $r[$k] = $v;
          break;
        case 'BOOLEAN':
          $r[$k] = $v?'true':'false';
          break;
        default:
          $r[$k] = $v;
      }
    }
    
    return $r;
  }
}