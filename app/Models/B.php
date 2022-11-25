<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Repository\Repository;

/**
 * BigQuery Model
 */
abstract class B extends Model
{
  const BQCASTS = []; //This const is overriden in extended classes

  /**
   * Extract changes, all fields must be present.
   * @return array [ 'table' => <tablename>, 'fields' => [ <Fieldname> => <Parsed prepared value for BigQuery>, ... ] ]
   */
  public function extractPreparedDatabaseChanges(): array
  {
    $r = [];
    $BQCASTS = $this::BQCASTS;
    $castedValues = Repository::valuesToCastedValues($BQCASTS,$this->attributes,false);

    foreach($BQCASTS as $fieldname => $cast) {
      if(\array_key_exists($fieldname, $castedValues)) {
        $r[$fieldname] = $castedValues[$fieldname];
      } else {
        $r[$fieldname] = NULL; //NULL when not defined
      }
    }
    return ['table' => $this->table, 'fields' => $r];
  }
}