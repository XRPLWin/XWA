<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenCancelOffer.
 * PK: rAcct-29 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenCancelOffer extends BTransaction
{
  const TYPE = 29;
  const TYPENAME = 'NFTokenCancelOffer';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}