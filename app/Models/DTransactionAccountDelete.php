<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-4 SK: <INT> (Ledger index)
 */
final class DTransactionAccountDelete extends DTransaction
{
  const TYPE = 4;
}