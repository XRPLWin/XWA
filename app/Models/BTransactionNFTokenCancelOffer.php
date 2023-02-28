<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenCancelOffer.
 * PK: rAcct-28 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenCancelOffer extends BTransaction
{
  const TYPE = 28;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}