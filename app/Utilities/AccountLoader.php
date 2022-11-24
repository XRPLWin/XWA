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
    $AccountArray = null; //remove this

    if(!$AccountArray) {
      $Account = BAccount::find($address);
      if(!$Account)
        return null;
      Cache::put('daccount:'.$address, $Account->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $Account = new BAccount($AccountArray);
      $Account->exists = true;
    }
    return $Account;
  }
}