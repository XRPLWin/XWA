<?php

namespace App\Models;

/**
 * Transaction model of OfferCreate sub-type.
 * PK: rAcct-8 SK: <INT> (Ledger index)
 */
class BTransactionOfferCreate_Trade extends BTransactionOfferCreate
{
  const TYPE = 8;
  const TYPENAME = 'OfferCreate (Trade)';
}