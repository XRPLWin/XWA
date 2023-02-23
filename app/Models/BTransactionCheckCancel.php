<?php

namespace App\Models;

/**
 * Transaction model of type CheckCancel.
 * PK: rAcct-18 SK: <INT> (Ledger index)
 */
class BTransactionCheckCancel extends BTransaction
{
  const TYPE = 18;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}