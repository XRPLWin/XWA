<?php

namespace App\Models;

/**
 * Transaction model of type DelegateSet.
 * PK: rAcct-63 SK: <INT> (Ledger index)
 */
class BTransactionDelegateSet extends BTransaction
{
  const TYPE = 63;
  const TYPENAME = 'DelegateSet';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}