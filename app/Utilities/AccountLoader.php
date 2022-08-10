<?php

namespace App\Utilities;
use App\Models\DAccount;

class AccountLoader
{
  /**
   * Fetches DAccount model or creates new in DB.
   * @return DAccount
   * @throws Exception
   */
  public static function getOrCreate(string $address): DAccount
  {
    # Validation
    $l = \strlen($address);
    if($l < 25 || $l > 35)
      throw new \Exception('Address is incorrect format');
    
    $Account = DAccount::find(['PK' => $address, 'SK' => 0]);
    //dd($Account->toDynamoDbQuery(),$Account->address);
    if(!$Account)
    {
      $Account = new DAccount();
      $Account->PK = $address;
      $Account->SK = 0;
      $Account->l = 0; // Ledger index this account is scanned to.
      $Account->save();
    }

    return $Account;
  }
}