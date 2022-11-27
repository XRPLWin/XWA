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
   * Extract changes, all fields must be present for insert, part can be present onl for update.
   * @return array [ 'table' => <tablename>, 'method' => <update|insert> 'fields' => [ <Fieldname> => <Parsed prepared value for BigQuery>, ... ], 'model' => MODEL ]
   */
  public function extractPreparedDatabaseChanges(): array
  {
    $r = [];
    $BQCASTS = $this::BQCASTS;
    $castedValues = Repository::valuesToCastedValues($BQCASTS,$this->attributes,$this->exists);
    
    if($this->exists) { //update
      //extract only dirty changed values
      foreach($this->getDirty() as $fieldname => $foo) {
        $r[$fieldname] = $castedValues[$fieldname];
      }
    } else { //insert
      foreach($BQCASTS as $fieldname => $foo) {
        if(\array_key_exists($fieldname, $castedValues)) {
          $r[$fieldname] = $castedValues[$fieldname];
        } else {
          $r[$fieldname] = NULL; //NULL when not defined
        }
      }
    }
    
    return ['table' => $this->table, 'method' => $this->exists ? 'update':'insert', 'fields' => $r, 'model' => $this];
  }
}