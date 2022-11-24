<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Repository\AccountsRepository;


use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

class BAccount extends Model
{
  protected $table = 'accounts';
  protected $primaryKey = 'address';
  protected $keyType = 'string';

  public $fillable = [
    'address', //Primary Key
    'l'
  ];

  public static function find(string $address): ?self
  {
    $data = AccountsRepository::fetchByAddress($address);
    if($data === null)
      return null;

    $model = new self($data);
    $model->exists = true;
    return $model;
  }

  public static function insert(array $values): bool
  {
    return AccountsRepository::insert($values);
  }

  public function save(array $options = [])
  {
    $data = $this->toArray();

    if($this->exists) {
      throw new \Exception('Update Not implemented');
    }

    return AccountsRepository::insert($data);
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
