<?php

namespace App\Models;

/**
 * Transaction model of type DepositPreauth.
 * PK: rAcct-22 SK: <INT> (Ledger index)
 */
class BTransactionDepositPreauth_Authorize extends BTransaction
{
  const TYPE = 22;
  const TYPENAME = 'DepositPreauth (Authorize)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}