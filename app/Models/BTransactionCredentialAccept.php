<?php

namespace App\Models;

/**
 * Transaction model of type CredentialAccept.
 * PK: rAcct-67 SK: <INT> (Ledger index)
 */
class BTransactionCredentialAccept extends BTransaction
{
  const TYPE = 67;
  const TYPENAME = 'CredentialAccept';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}