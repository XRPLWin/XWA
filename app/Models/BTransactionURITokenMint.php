<?php

namespace App\Models;

/**
 * Transaction model of type URITokenMint (claim). 
 * PK: rAcct-40 SK: <INT> (Ledger index)
 */
final class BTransactionURITokenMint extends BTransaction
{
  const TYPE = 40;
  const TYPENAME = 'URITokenMint';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}