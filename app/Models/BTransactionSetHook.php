<?php

namespace App\Models;

/**
 * Transaction model of type AccountDelete.
 * PK: rAcct-32 SK: <INT> (Ledger index)
 */
final class BTransactionSetHook extends BTransaction
{
  const TYPE = 32;
  const TYPENAME = 'SetHook';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}