<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenMint.
 * PK: rAcct-30 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenMint extends BTransaction
{
  const TYPE = 30;
  const TYPENAME = 'NFTokenMint';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}