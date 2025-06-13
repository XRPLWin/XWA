<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenModify.
 * PK: rAcct-61 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenModify extends BTransaction
{
  const TYPE = 61;
  const TYPENAME = 'NFTokenModify';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}