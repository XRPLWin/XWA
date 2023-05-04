<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenCreateOffer.
 * PK: rAcct-26 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenCreateOffer_Buy extends BTransaction
{
  const TYPE = 26;
  const TYPENAME = 'NFTokenCreateOffer (Buy)';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);

    if(isset($array['c']) && $array['c'] !== null) {
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    
    return $array;
  }

}