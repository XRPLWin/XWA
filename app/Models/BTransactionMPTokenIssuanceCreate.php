<?php

namespace App\Models;

/**
 * Transaction model of type MPTokenIssuanceCreate.
 * PK: rAcct-64 SK: <INT> (Ledger index)
 */
class BTransactionMPTokenIssuanceCreate extends BTransaction
{
  const TYPE = 65;
  const TYPENAME = 'MPTokenIssuanceCreate';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}