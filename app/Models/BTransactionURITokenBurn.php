<?php

namespace App\Models;

/**
 * Transaction model of type URITokenBurn. 
 * PK: rAcct-45 SK: <INT> (Ledger index)
 */
final class BTransactionURITokenBurn extends BTransaction
{
  const TYPE = 45;
  const TYPENAME = 'URITokenBurn';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}