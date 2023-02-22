<?php

namespace App\Models;

/**
 * Transaction model of type BTransactionSignerListSet.
 * PK: rAcct-15 SK: <INT> (Ledger index)
 */
class BTransactionSignerListSet extends BTransaction
{
  const TYPE = 15;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}