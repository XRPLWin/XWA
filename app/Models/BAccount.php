<?php

namespace App\Models;

use App\Repository\AccountsRepository;


use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

class BAccount extends B
{
  protected $table = 'accounts';
  protected $primaryKey = 'address';
  protected $keyType = 'string';
  public $timestamps = false;

  public $fillable = [
    'address', //Primary Key
    'l',
    'activatedBy',
    'isdeleted'
  ];

  const BQCASTS = [
    'address' => 'STRING',
    'l'       => 'INTEGER',
    'activatedBy' => 'STRING',
    'isdeleted' => 'BOOLEAN'
  ];

  public static function boot()
  {
      parent::boot();
      static::saved(function (DAccount $model) {
        $model->flushCache();
      });
      static::deleted(function (DAccount $model) {
        $model->flushCache();
      });
  }

  public function flushCache()
  {
    dd('test');
    Cache::forget('daccount:'.$this->PK);
    Cache::forget('daccount_fti:'.$this->PK);
  }

  public static function find(string $address): ?self
  {
    $data = AccountsRepository::fetchByAddress($address);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function insert(array $values): bool
  {
    return AccountsRepository::insert($values);
  }

  /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save_todo(array $options = [])
    {
        $this->mergeAttributesFromCachedCasts();

        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ?
                $this->performUpdate($query) : true;
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query);

            if (! $this->getConnectionName() &&
                $connection = $query->getConnection()) {
                $this->setConnection($connection->getName());
            }
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
   * Saves model to database, update or insert.
   * @return ?bool true: succese update or insert, false: error, null no changes when trying to update.
   */
  public function save(array $options = []): ?bool
  {
    $data = $this->extractPreparedDatabaseChanges();
    if($data['method'] == 'update') {
      return AccountsRepository::update($data['table'], 'address = """'.$this->address.'"""', ['fields' => $data['fields'], 'model' => $this]);
    }
    return AccountsRepository::insert($data['fields']);
  }

  /**
   * Queries XRPLedger to determine if this account is issuer.
   * @return bool
   */
  /*public function checkIsIssuer(): bool
  {
    //get if this account is issuer or not by checking obligations
    $gateway_balances = app(XRPLWinApiClient::class)
        ->api('gateway_balances')
        ->params([
            'account' => $this->address,
            'strict' => true,
            'ledger_index' => 'validated',
        ])
        ->send()
        ->finalResult();

    if(isset($gateway_balances->obligations) && !empty($gateway_balances->obligations))
      return true;

    return false;
  }*/
}
