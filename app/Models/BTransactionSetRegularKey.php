<?php

namespace App\Models;

/**
 * Transaction model of type SetRegularKey.
 * PK: rAcct-11 SK: <INT> (Ledger index)
 */
class BTransactionSetRegularKey extends BTransaction
{
  const TYPE = 11;
  const TYPENAME = 'SetRegularKey';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}