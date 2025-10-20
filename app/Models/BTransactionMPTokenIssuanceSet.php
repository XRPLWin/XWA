<?php

namespace App\Models;

/**
 * Transaction model of type MPTokenIssuanceSet.
 * PK: rAcct-70 SK: <INT> (Ledger index)
 */
class BTransactionMPTokenIssuanceSet extends BTransaction
{
  const TYPE = 70;
  const TYPENAME = 'MPTokenIssuanceSet';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}