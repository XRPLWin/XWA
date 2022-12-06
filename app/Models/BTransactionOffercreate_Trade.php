<?php

namespace App\Models;

/**
 * Transaction model of OfferCreate sub-type.
 * PK: rAcct-8 SK: <INT> (Ledger index)
 */
class BTransactionOffercreate_Trade extends BTransactionOffercreate
{
  const TYPE = 8;
}