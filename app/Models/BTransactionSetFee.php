<?php

namespace App\Models;

/**
 * Transaction model of type SetFee. 
 * PK: rAcct-42 SK: <INT> (Ledger index)
 */
final class BTransactionSetFee extends BTransaction
{
  const TYPE = 42;
  const TYPENAME = 'SetFee';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}