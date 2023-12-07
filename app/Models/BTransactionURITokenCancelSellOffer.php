<?php

namespace App\Models;

/**
 * Transaction model of type URITokenCancelSellOffer. 
 * PK: rAcct-46 SK: <INT> (Ledger index)
 */
final class BTransactionURITokenCancelSellOffer extends BTransaction
{
  const TYPE = 46;
  const TYPENAME = 'URITokenCancelSellOffer';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}