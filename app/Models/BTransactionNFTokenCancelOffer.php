<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenCancelOffer.
 * PK: rAcct-29 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenCancelOffer extends BTransaction
{
  const TYPE = 29;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}