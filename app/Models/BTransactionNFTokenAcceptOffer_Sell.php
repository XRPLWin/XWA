<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenAcceptOffer.
 * PK: rAcct-27 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenAcceptOffer_Sell extends BTransactionNFTokenAcceptOffer_Buy
{
  const TYPE = 27;

}