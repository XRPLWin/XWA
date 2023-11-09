<?php

namespace App\Models;

/**
 * Transaction model of type ClaimReward (opt out). 
 * PK: rAcct-37 SK: <INT> (Ledger index)
 */
final class BTransactionClaimReward_OptOut extends BTransaction
{
  const TYPE = 37;
  const TYPENAME = 'ClaimReward (opt out)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}