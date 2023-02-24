<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenCreateOffer.
 * PK: rAcct-25 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenCreateOffer extends BTransaction
{
  const TYPE = 25;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}