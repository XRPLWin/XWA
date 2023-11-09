<?php

namespace App\Models;

/**
 * Transaction model of type Import (B2M). 
 * PK: rAcct-36 SK: <INT> (Ledger index)
 */
final class BTransactionImport extends BTransaction
{
  const TYPE = 36;
  const TYPENAME = 'Import';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);

    //any context here?
    
    return $array;
  }
}