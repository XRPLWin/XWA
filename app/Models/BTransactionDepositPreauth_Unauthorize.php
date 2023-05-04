<?php

namespace App\Models;

/**
 * Transaction model of type DepositPreauth.
 * PK: rAcct-23 SK: <INT> (Ledger index)
 */
class BTransactionDepositPreauth_Unauthorize extends BTransaction
{
  const TYPE = 23;
  const TYPENAME = 'DepositPreauth (Unauthorize)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}