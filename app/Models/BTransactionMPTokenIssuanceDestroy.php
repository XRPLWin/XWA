<?php

namespace App\Models;

/**
 * Transaction model of type MPTokenIssuanceDestroy.
 * PK: rAcct-69 SK: <INT> (Ledger index)
 */
class BTransactionMPTokenIssuanceDestroy extends BTransaction
{
  const TYPE = 69;
  const TYPENAME = 'MPTokenIssuanceDestroy';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}