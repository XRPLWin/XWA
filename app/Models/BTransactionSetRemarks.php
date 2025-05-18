<?php

namespace App\Models;

/**
 * Transaction model of type SetRemarks.
 * PK: rAcct-60 SK: <INT> (Ledger index)
 */
class BTransactionSetRemarks extends BTransaction
{
  const TYPE = 60;
  const TYPENAME = 'SetRemarks';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}