<?php

namespace App\Models;

/**
 * Transaction model of type Activation.
 * PK: rAcct-2 SK: <INT> (Ledger index)
 */
final class BTransactionActivation extends BTransaction
{
  const TYPE = 2;
}