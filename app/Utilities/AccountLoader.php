<?php

namespace App\Utilities;
use App\Models\DTransaction;

class AccountLoader
{
  /**
   * Fetches DTransaction model or creates new in DB.
   * @return DTransaction
   * @throws Exception
   */
  public static function getOrCreate(string $address)
  {
    //Validation
    $l = \strlen($address);
    if($l < 25 || $l > 35)
      throw new \Exception('Address is incorrect format');
    
    $Account = DTransaction::find(['PK' => $address, 'SK' => 0]);
    //dd($Account->toDynamoDbQuery(),$Account->address);
    if(!$Account)
    {
      $Account = new DTransaction();
      $Account->PK = $address;
      $Account->SK = 0;
      $Account->l = 0; // Ledger index this account is scanned to.
      //$Account->customval = 'test123';
      $Account->save();
    }

    return $Account;
    
  }
  //TODO
  public static function getOrCreatePayment(string $address, int $ledger_index)
  {
    $model = DTransaction::find(['PK' => $address.'-'.DTransaction::TX_PAYMENT, 'SK' => $ledger_index]);
    if(!$model)
    {
      $model = new DTransaction();
      $model->PK = $address.'-'.DTransaction::TX_PAYMENT;
      $model->SK = $ledger_index;
      $model->customval = 'test123';
      $model->save();
    }
    return $model;
  }

  
}