<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenAcceptOffer.
 * PK: rAcct-28 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenAcceptOffer_Buy extends BTransaction
{
  const TYPE = 28;
  const TYPENAME = 'NFTokenAcceptOffer (Buy)';

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