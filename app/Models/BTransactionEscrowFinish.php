<?php

namespace App\Models;

/**
 * Transaction model of type EscrowFinish.
 * PK: rAcct-20 SK: <INT> (Ledger index)
 */
class BTransactionEscrowFinish extends BTransaction
{
  const TYPE = 20;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}