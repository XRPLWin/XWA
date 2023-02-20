<?php

namespace App\Models;

/**
 * Transaction model of type OfferCreate.
 * PK: rAcct-13 SK: <INT> (Ledger index)
 */
class BTransactionPaymentChannelClaim extends BTransaction
{
  const TYPE = 13;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}