<?php

namespace App\Models;

/**
 * Transaction model of type CredentialCreate.
 * PK: rAcct-64 SK: <INT> (Ledger index)
 */
class BTransactionCredentialCreate extends BTransaction
{
  const TYPE = 64;
  const TYPENAME = 'CredentialCreate';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}