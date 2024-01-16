<?php

namespace App\Models;

/**
 * Transaction model of type ClaimReward. 
 * This can result as Sucessfull claim, or failed claim.
 * PK: rAcct-39 SK: <INT> (Ledger index)
 */
final class BTransactionClaimReward extends BTransaction
{
  const TYPE = 39;
  const TYPENAME = 'ClaimReward';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}