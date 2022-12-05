<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Repository\Repository;

/**
 * BigQuery Model
 */
abstract class B extends Model
{
  //protected $connection = 'bigquery';
  const BQCASTS = []; //This const is overriden in extended classes

  public string $repositoryclass;

  /*public function test()
  {
    return new $this->repositoryclass;
  }*/

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
    
    return ['table' => $this->getTable(), 'method' => $this->exists ? 'update':'insert', 'fields' => $r, 'model' => $this];
  }

  protected function bqPrimaryKeyCondition(): string
  {
    throw new \Exception('Not implemented');
  }

  /**
   * Row is inserted to database, apply changes to this model.
   * Saving event wont trigger when batch inserting only finishSave
   * Called from Repository/Batch
   */
  public function applyInsertedEvent($options = [])
  {
    $this->mergeAttributesFromCachedCasts();
    $this->finishSave($options);
  }

  /**
   * Save the model to the database.
   *
   * @param  array  $options
   * @return bool
   */
  public function save(array $options = [])
  {
    $this->mergeAttributesFromCachedCasts();
    
    //$query = $this->newModelQuery();

    // If the "saving" event returns false we'll bail out of the save and return
    // false, indicating that the save failed. This provides a chance for any
    // listeners to cancel save operations if validations fail or whatever.
    if ($this->fireModelEvent('saving') === false) {
      return false;
    }

    $data = $this->extractPreparedDatabaseChanges();
    //dd($data);
    
    // If the model already exists in the database we can just update our record
    // that is already in this database using the current IDs in this "where"
    // clause to only update this model. Otherwise, we'll just insert them.
    if ($this->exists) {
      $saved = $this->isDirty() ?
        $this->performBQUpdate($data) : true;
    }

    // If the model is brand new, we'll insert it into our database and set the
    // ID attribute on the model to the value of the newly inserted row's ID
    // which is typically an auto-increment value managed by the database.
    else {

      //do update and set $saved = bool

      $saved = $this->performBQInsert($data);

      //if (! $this->getConnectionName() &&
      //    $connection = $query->getConnection()) {
      //    $this->setConnection($connection->getName());
      //}
    }

    // If the model is successfully saved, we need to do a few more things once
    // that is done. We will call the "saved" method here to run any actions
    // we need to happen after a model gets successfully saved right here.
    if ($saved) {
      $this->finishSave($options);
    }

    return $saved;
  }

  /**
   * Perform a model update operation.
   *
   * @param  array $data
   * @return bool
   */
  protected function performBQUpdate(array $data)
  {
    // If the updating event returns false, we will cancel the update operation so
    // developers can hook Validation systems into their models and cancel this
    // operation if the model does not pass validation. Otherwise, we update.
    if ($this->fireModelEvent('updating') === false) {
      return false;
    }

    // First we need to create a fresh query instance and touch the creation and
    // update timestamp on the model which are maintained by us for developer
    // convenience. Then we will just continue saving the model instances.
    //if ($this->usesTimestamps()) {
    //    $this->updateTimestamps();
    //}

    // Once we have run the update operation, we will fire the "updated" event for
    // this model instance. This will allow developers to hook into these after
    // models are updated, giving them a chance to do any special processing.
    $dirty = $this->getDirty();
    $saved = true;

    if (count($dirty) > 0) {
      //$this->setKeysForSaveQuery($query)->update($dirty);
    
      $saved = $this->repositoryclass::update(
        $data['table'],
        $this->bqPrimaryKeyCondition(),
        ['fields' => $data['fields'], 'model' => $this]
      );

      if($saved === true) {
        $this->syncChanges();
        $this->fireModelEvent('updated', false);
      }
    }

    return $saved;
  }


  /**
   * Perform a model insert operation.
   *
   * @param  array $data
   * @return bool
   */
  protected function performBQInsert(array $data)
  {
    if ($this->fireModelEvent('creating') === false) {
      return false;
    }

    // First we'll need to create a fresh query instance and touch the creation and
    // update timestamps on this model, which are maintained by us for developer
    // convenience. After, we will just continue saving these model instances.
    //if ($this->usesTimestamps()) {
    //    $this->updateTimestamps();
    //}

    
    $saved = $this->repositoryclass::insert($data['fields']);
    if(!$saved)
      return false;

    // If the model has an incrementing key, we can use the "insertGetId" method on
    // the query builder, which will give us back the final inserted ID for this
    // table from the database. Not all tables have to be incrementing though.
    //$attributes = $this->getAttributesForInsert();
    //dd($attributes);

    //if ($this->getIncrementing()) {
    //    $this->insertAndSetId($query, $attributes);
    //}

    // If the table isn't incrementing we'll simply insert these attributes as they
    // are. These attribute arrays must contain an "id" column previously placed
    // there by the developer as the manually determined key for these models.
    //else {
    //    if (empty($attributes)) {
    //        return true;
    //    }
    //    $query->insert($attributes);
    //}

    // We will go ahead and set the exists property to true, so that it is set when
    // the created event is fired, just in case the developer tries to update it
    // during the event. This will allow them to do so and run an update here.
    $this->exists = true;

    $this->wasRecentlyCreated = true;

    $this->fireModelEvent('created', false);

    return true;
  }
}