<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of Payment sub-type.
 * PK: rAcct-5 SK: <INT> (Ledger index)
 */
final class DTransactionPayment_BalanceChange extends DTransactionPayment
{
  const TYPE = 5;
}