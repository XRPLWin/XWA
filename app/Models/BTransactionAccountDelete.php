<?php

namespace App\Models;

/**
 * Transaction model of type AccountDelete.
 * PK: rAcct-4 SK: <INT> (Ledger index)
 */
final class BTransactionAccountDelete extends BTransaction
{
  const TYPE = 4;
}