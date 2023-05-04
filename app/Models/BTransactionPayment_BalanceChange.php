<?php

namespace App\Models;

/**
 * Transaction model of Payment sub-type.
 * PK: rAcct-5 SK: <INT> (Ledger index)
 */
final class BTransactionPayment_BalanceChange extends BTransactionPayment
{
  const TYPE = 5;
  const TYPENAME = 'Payment (BalanceChange)';
}