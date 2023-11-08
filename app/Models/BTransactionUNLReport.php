<?php

namespace App\Models;

/**
 * Transaction model of type UNLReport.
 * PK: rAcct-35 SK: <INT> (Ledger index)
 */
final class BTransactionUNLReport extends BTransaction
{
  const TYPE = 35;
  const TYPENAME = 'UNLReport';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    return $array;
  }
}