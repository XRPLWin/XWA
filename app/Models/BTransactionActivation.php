<?php

namespace App\Models;

/**
 * Transaction model of type Activation.
 * PK: rAcct-2 SK: <INT> (Ledger index)
 */
final class BTransactionActivation extends BTransaction
{
  const TYPE = 2;
  const TYPENAME = 'Activation';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}