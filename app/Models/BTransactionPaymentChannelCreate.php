<?php

namespace App\Models;

/**
 * Transaction model of type PaymentChannelCreate.
 * PK: rAcct-12 SK: <INT> (Ledger index)
 */
class BTransactionPaymentChannelCreate extends BTransaction
{
  const TYPE = 12;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}