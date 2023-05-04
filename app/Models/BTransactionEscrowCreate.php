<?php

namespace App\Models;

/**
 * Transaction model of type EscrowCreate.
 * PK: rAcct-19 SK: <INT> (Ledger index)
 */
class BTransactionEscrowCreate extends BTransaction
{
  const TYPE = 19;
  const TYPENAME = 'EscrowCreate';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}