<?php

namespace App\Models;

/**
 * Transaction model of type MPTokenAuthorize.
 * PK: rAcct-68 SK: <INT> (Ledger index)
 */
class BTransactionMPTokenAuthorize extends BTransaction
{
  const TYPE = 68;
  const TYPENAME = 'MPTokenAuthorize';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}