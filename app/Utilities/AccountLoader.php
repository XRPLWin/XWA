<?php

namespace App\Utilities;
use App\Models\DAccount;
use Illuminate\Support\Facades\Cache;

class AccountLoader
{
  /**
   * Fetches DAccount model or creates new in DB.
   * @return DAccount
   * @throws \Exception
   */
  public static function getOrCreate(string $address): DAccount
  {
    $Account = self::get($address);
    
    if(!$Account)
    {
      $Account = new DAccount();
      $Account->PK = $address; // @phpstan-ignore-line
      //$Account->SK = 0; // @phpstan-ignore-line
      // Ledger index this account is scanned to.
      $Account->l = config('xrpl.genesis_ledger');  // @phpstan-ignore-line
      //$Account->t = <INT>; //account type: undefined (default) - normal, 1 - issuer
      $Account->save();
    }

    return $Account;
  }

  /**
   * Gets DAccount from cache or database.
   * @return DAccount|null
   */
  public static function get(string $address): ?DAccount
  {
    validateXRPAddressOrFail($address);
    
    $AccountArray = Cache::get('daccount_'.$address);
   
    if(!$AccountArray) {
    $Account = DAccount::find(['PK' => $address/*, 'SK' => 0*/]);

      if(!$Account)
        return null;
      Cache::put('daccount_'.$address, $Account->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $Account = new DAccount($AccountArray);
      $Account->exists = true;
      $Account->setTable($Account->getTable());
    }
    return $Account;
  }
}