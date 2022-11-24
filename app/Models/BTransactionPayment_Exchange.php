<?php

namespace App\Models;

/**
 * Transaction model of Payment sub-type.
 * PK: rAcct-6 SK: <INT> (Ledger index)
 */
final class BTransactionPayment_Exchange extends BTransactionPayment
{
  const TYPE = 6;
}