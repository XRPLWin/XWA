<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenMint.
 * PK: rAcct-30 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenMint extends BTransaction
{
  const TYPE = 30;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}