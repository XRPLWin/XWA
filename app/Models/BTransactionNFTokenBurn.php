<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenBurn.
 * PK: rAcct-31 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenBurn extends BTransaction
{
  const TYPE = 31;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}