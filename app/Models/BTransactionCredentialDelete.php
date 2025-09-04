<?php

namespace App\Models;

/**
 * Transaction model of type CredentialDelete.
 * PK: rAcct-66 SK: <INT> (Ledger index)
 */
class BTransactionCredentialDelete extends BTransaction
{
  const TYPE = 66;
  const TYPENAME = 'CredentialDelete';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}