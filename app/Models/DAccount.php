<?php

namespace App\Models;

use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

/**
 * DynamoDB Transaction Account information.
 */
final class DAccount extends DTransaction
{
  //No TYPE
  public $fillable = [
    'PK', //Primary Key
    'SK', //Sort Key
    'l',  //Last synced ledger_index
    'by', //Activated by
    't',  //account internal type, undefined - normal, 1 - issuer
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
    Cache::forget('daccount_'.$this->PK);
  }

  /**
   * Synces job if not already synced
   * @return bool True if added to sync queue (queued)
   */
  public function sync(bool $recursive = true): bool
  {
    //check if already synced
    $check = DB::connection(config('database.default'))
      ->table('jobs')
      ->where('qtype','account')
      ->where('qtype_data',$this->address)
      ->count();
    
    if($check)
      return false;
    
    QueueArtisanCommand::dispatch(
      'xwa:accountsync',
      ['address' => $this->address, '--recursiveaccountqueue' => $recursive ],
      'account',
      $this->address
    )->onQueue('default'); //todo replace default with sync
    return true;
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

  /**
   * Queries XRPLedger to determine if this account is issuer.
   * @return bool
   */
  public function checkIsIssuer(): bool
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
  }

}
