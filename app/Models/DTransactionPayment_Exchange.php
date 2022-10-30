<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of Payment sub-type.
 * PK: rAcct-6 SK: <INT> (Ledger index)
 */
final class DTransactionPayment_Exchange extends DTransactionPayment
{
  const TYPE = 6;
}