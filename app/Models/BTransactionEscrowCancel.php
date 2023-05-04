<?php

namespace App\Models;

/**
 * Transaction model of type EscrowCancel.
 * PK: rAcct-21 SK: <INT> (Ledger index)
 */
class BTransactionEscrowCancel extends BTransaction
{
  const TYPE = 21;
  const TYPENAME = 'EscrowCancel';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}