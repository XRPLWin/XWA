<?php

namespace App\Models;

/**
 * Transaction model of type PaymentChannelFund.
 * PK: rAcct-14 SK: <INT> (Ledger index)
 */
class BTransactionPaymentChannelFund extends BTransaction
{
  const TYPE = 14;
  const TYPENAME = 'PaymentChannelFund';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}