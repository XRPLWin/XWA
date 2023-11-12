<?php

namespace App\Models;

/**
 * Transaction model of type EmitFailure. 
 * PK: rAcct-43 SK: <INT> (Ledger index)
 */
final class BTransactionEmitFailure extends BTransaction
{
  const TYPE = 43;
  const TYPENAME = 'EmitFailure';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}