<?php

namespace App\Models;

/**
 * Transaction model of type AccountDelete.
 * PK: rAcct-4 SK: <INT> (Ledger index)
 */
final class BTransactionAccountDelete extends BTransaction
{
  const TYPE = 4;
  const TYPENAME = 'AccountDelete';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}