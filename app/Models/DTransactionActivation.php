<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-1 SK: <INT> (Ledger index)
 */
final class DTransactionActivation extends DTransaction
{
  const TYPE = 2;
}