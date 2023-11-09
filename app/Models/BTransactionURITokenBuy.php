<?php

namespace App\Models;

/**
 * Transaction model of type URITokenBuy. 
 * PK: rAcct-41 SK: <INT> (Ledger index)
 */
final class BTransactionURITokenBuy extends BTransaction
{
  const TYPE = 41;
  const TYPENAME = 'URITokenBuy';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}