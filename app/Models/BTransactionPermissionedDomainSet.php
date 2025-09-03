<?php

namespace App\Models;

/**
 * Transaction model of type PermissionedDomainSet.
 * PK: rAcct-62 SK: <INT> (Ledger index)
 */
class BTransactionPermissionedDomainSet extends BTransaction
{
  const TYPE = 62;
  const TYPENAME = 'PermissionedDomainSet';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    return $array;
  }

}