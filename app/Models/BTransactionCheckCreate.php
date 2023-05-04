<?php

namespace App\Models;

/**
 * Transaction model of type CheckCreate.
 * PK: rAcct-16 SK: <INT> (Ledger index)
 */
class BTransactionCheckCreate extends BTransaction
{
  const TYPE = 16;
  const TYPENAME = 'CheckCreate';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}