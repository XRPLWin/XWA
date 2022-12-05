<?php

namespace App\Utilities;
use App\Models\DAccount;
use App\Models\BAccount;
use Illuminate\Support\Facades\Cache;

class AccountLoader
{
  /**
   * Fetches DAccount model or creates new in DB.
   * @return BAccount
   * @throws \Exception
   */
  public static function getOrCreate(string $address): BAccount
  {
    $Account = self::get($address);
    if(!$Account)
    {
      $Account = new BAccount([
        'address' => $address,
        'l' => (int)config('xrpl.genesis_ledger'), //initial
        'lt' => ripple_epoch_to_carbon(config('xrpl.genesis_ledger_close_time'))->format('Y-m-d H:i:s.uP'),
        'activatedBy' => null,
        'isdeleted' => false,
      ]);
      
      
      $Account->save();
    }

    return $Account;
  }

  /**
   * Gets DAccount from cache or database.
   * @return ?BAccount
   */
  public static function get(string $address): ?BAccount
  {
    validateXRPAddressOrFail($address);

    $AccountArray = Cache::get('daccount:'.$address);
    
    if(!$AccountArray) {
      $Account = BAccount::find($address);
      if(!$Account)
        return null;
      Cache::put('daccount:'.$address, $Account->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $Account = BAccount::hydrate([$AccountArray])->first();
    }
    return $Account;
  }
}