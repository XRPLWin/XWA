<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-1 SK: <INT> (Ledger index)
 */
final class DTransactionPayment extends DTransaction
{
  const TYPE = 1;
}