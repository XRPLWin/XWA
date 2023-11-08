<?php

namespace App\Models;

/**
 * Transaction model of type EnableAmendment.
 * PK: rAcct-34 SK: <INT> (Ledger index)
 */
final class BTransactionEnableAmendment extends BTransaction
{
  const TYPE = 34;
  const TYPENAME = 'EnableAmendment';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}