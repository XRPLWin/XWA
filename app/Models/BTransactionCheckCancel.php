<?php

namespace App\Models;

/**
 * Transaction model of type CheckCancel.
 * PK: rAcct-18 SK: <INT> (Ledger index)
 */
class BTransactionCheckCancel extends BTransaction
{
  const TYPE = 18;
  const TYPENAME = 'CheckCancel';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}