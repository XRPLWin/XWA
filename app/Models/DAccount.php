<?php

namespace App\Models;

use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;

/**
 * DynamoDB Transaction Account information.
 */
final class DAccount extends DTransaction
{
  //No TYPE
  public $fillable = ['PK','SK','l','by'];

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
    Cache::forget('daccount_'.$this->PK);
  }


  public function sync(bool $recursive = true)
  {
    //check if already synced
    $check = DB::connection(config('database.default'))
      ->table('jobs')
      ->where('qtype','account')
      ->where('qtype_data',$this->address)
      ->count();
    
    if($check)
      return;
    
    QueueArtisanCommand::dispatch(
      'xwa:accountsync',
      ['address' => $this->address, '--recursiveaccountqueue' => $recursive ],
      'account',
      $this->address
    )->onQueue('default'); //todo replace default with sync
  }

  /**
   * Is account synced currently.
   */
  public function isSynced($leeway_ledgers = 1)
  {
    $current_ledger = Ledger::current();
    $l = (int)$this->l;
    $check = $l + $leeway_ledgers;
    if($check <= $current_ledger)
      return false;
    return true;
  }

  /**
   * Is account synced in last 60 ledgers (about 10 minutes).
   * @return bool
   */
  public function isSyncedRecently(): bool
  {
    return $this->isSynced(60);
  }

}
