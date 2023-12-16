<?php

namespace App\Models;

/**
 * Transaction model of type UNLModify.
 * PK: rAcct-48 SK: <INT> (Ledger index)
 */
final class BTransactionUNLModify extends BTransaction
{
  const TYPE = 48;
  const TYPENAME = 'UNLModify';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}