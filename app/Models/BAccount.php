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
  protected $repositoryclass = AccountsRepository::class;

  public $fillable = [
    'address', //Primary Key
    'l',
    'activatedBy',
    'isdeleted'
  ];

  const BQCASTS = [
    'address' => 'STRING',
    'l'       => 'INTEGER',
    'activatedBy' => 'NULLABLE STRING',
    'isdeleted' => 'BOOLEAN'
  ];

  public static function boot()
  {
      parent::boot();
      static::saved(function (BAccount $model) {
        $model->flushCache();
      });
      static::deleted(function (BAccount $model) {
        $model->flushCache();
      });
  }

  public function flushCache()
  {
    Cache::forget('daccount:'.$this->address);
    Cache::forget('daccount_fti:'.$this->address);
  }

  protected function bqPrimaryKeyCondition(): string
  {
    return 'address = """'.$this->address.'"""';
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
