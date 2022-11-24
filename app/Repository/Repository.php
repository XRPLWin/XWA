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
  protected static function valuesToCastedValues(array $definition, array $values): array
  {
    $r = [];
    foreach($values as $k => $v) {
      $cast = $definition[$k];

      switch ($cast) {
        case 'BIGNUMERIC':
          $r[$k] = \BigQuery::bigNumeric($v);
          break;
        case 'STRING':
          $r[$k] =  '"""'.$v.'"""'; //tripple quote escape
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