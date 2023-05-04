<?php

namespace App\Models;

/**
 * Transaction model of type PaymentChannelClaim.
 * PK: rAcct-13 SK: <INT> (Ledger index)
 */
class BTransactionPaymentChannelClaim extends BTransaction
{
  const TYPE = 13;
  const TYPENAME = 'PaymentChannelClaim';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}