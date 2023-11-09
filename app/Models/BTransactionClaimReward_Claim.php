<?php

namespace App\Models;

/**
 * Transaction model of type ClaimReward (claim). 
 * PK: rAcct-39 SK: <INT> (Ledger index)
 */
final class BTransactionClaimReward_Claim extends BTransaction
{
  const TYPE = 39;
  const TYPENAME = 'ClaimReward (claim)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}