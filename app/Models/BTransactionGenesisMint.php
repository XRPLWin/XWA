<?php

namespace App\Models;

/**
 * Transaction model of type GenesisMint. 
 * PK: rAcct-47 SK: <INT> (Ledger index)
 */
final class BTransactionGenesisMint extends BTransaction
{
  const TYPE = 47;
  const TYPENAME = 'GenesisMint';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}