<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenBurn.
 * PK: rAcct-31 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenBurn extends BTransaction
{
  const TYPE = 31;
  const TYPENAME = 'NFTokenBurn';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}