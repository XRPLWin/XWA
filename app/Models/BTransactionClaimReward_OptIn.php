<?php

namespace App\Models;

/**
 * Transaction model of type ClaimReward (opt in). 
 * PK: rAcct-38 SK: <INT> (Ledger index)
 */
final class BTransactionClaimReward_OptIn extends BTransaction
{
  const TYPE = 38;
  const TYPENAME = 'ClaimReward (opt in)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}