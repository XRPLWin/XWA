<?php

namespace App\Models;

/**
 * Transaction model of type BTransactionCheckCreate.
 * PK: rAcct-16 SK: <INT> (Ledger index)
 */
class BTransactionCheckCreate extends BTransaction
{
  const TYPE = 16;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}