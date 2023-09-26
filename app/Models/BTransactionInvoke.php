<?php

namespace App\Models;

/**
 * Transaction model of type AccountDelete.
 * PK: rAcct-33 SK: <INT> (Ledger index)
 */
final class BTransactionInvoke extends BTransaction
{
  const TYPE = 33;
  const TYPENAME = 'Invoke';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}