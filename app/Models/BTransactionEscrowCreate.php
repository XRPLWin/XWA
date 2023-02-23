<?php

namespace App\Models;

/**
 * Transaction model of type EscrowCreate.
 * PK: rAcct-19 SK: <INT> (Ledger index)
 */
class BTransactionEscrowCreate extends BTransaction
{
  const TYPE = 19;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}