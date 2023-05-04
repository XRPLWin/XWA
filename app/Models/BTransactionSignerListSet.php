<?php

namespace App\Models;

/**
 * Transaction model of type BTransactionSignerListSet.
 * PK: rAcct-15 SK: <INT> (Ledger index)
 */
class BTransactionSignerListSet extends BTransaction
{
  const TYPE = 15;
  const TYPENAME = 'SignerListSet';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}