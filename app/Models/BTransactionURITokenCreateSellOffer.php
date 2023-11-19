<?php

namespace App\Models;

/**
 * Transaction model of type URITokenCreateSellOffer. 
 * PK: rAcct-44 SK: <INT> (Ledger index)
 */
final class BTransactionURITokenCreateSellOffer extends BTransaction
{
  const TYPE = 44;
  const TYPENAME = 'URITokenCreateSellOffer';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}