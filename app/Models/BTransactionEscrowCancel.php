<?php

namespace App\Models;

/**
 * Transaction model of type EscrowCancel.
 * PK: rAcct-21 SK: <INT> (Ledger index)
 */
class BTransactionEscrowCancel extends BTransaction
{
  const TYPE = 21;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}