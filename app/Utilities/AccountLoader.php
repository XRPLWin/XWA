<?php

namespace App\Utilities;
use App\Models\BAccount;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AccountLoader
{
  /**
   * Fetches BAccount model or creates new in DB.
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
        'li' => 0,
        'lt' => ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'))->format('Y-m-d H:i:s.uP'),
        'activatedBy' => null,
        'isdeleted' => false,
      ]);
      
      $Account->save(); //this should be save or update
    }
    return $Account;
  }

  /**
   * Fetches BAccount model or creates new in DB. Locks it.
   * @return BAccount
   * @throws \Exception
   */
  public static function getForUpdateOrCreate(string $address): BAccount
  {
    Cache::delete('daccount:'.$address);
    Cache::forget('daccount_fti:'.$address);

    DB::beginTransaction();
    $Account = self::get($address,true);
    
    if(!$Account)
    {
      $Account = new BAccount([
        'address' => $address,
        'l' => (int)config('xrpl.genesis_ledger'), //initial
        'li' => 0,
        'lt' => ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'))->format('Y-m-d H:i:s.uP'),
        'activatedBy' => null,
        'isdeleted' => false,
      ]);
      $Account->save(); //this should be save or update
    }
    DB::commit();

    return $Account;
  }

  /**
   * Gets BAccount from cache or database.
   * @return ?BAccount
   */
  public static function get(string $address, bool $lockforupdate = false): ?BAccount
  {
    validateXRPAddressOrFail($address);
   
    $AccountArray = Cache::get('daccount:'.$address);
    if($AccountArray == null) {
      $Account = BAccount::repo_find($address,$lockforupdate);
      if(!$Account)
        return null;
      Cache::put('daccount:'.$address, $Account->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $Account = BAccount::hydrate([$AccountArray])->first();
    }
    return $Account;
  }
}