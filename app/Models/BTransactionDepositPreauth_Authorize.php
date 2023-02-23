<?php

namespace App\Models;

/**
 * Transaction model of type DepositPreauth_Authorize.
 * PK: rAcct-22 SK: <INT> (Ledger index)
 */
class BTransactionDepositPreauth_Authorize extends BTransaction
{
  const TYPE = 22;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}