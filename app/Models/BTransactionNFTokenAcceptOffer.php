<?php

namespace App\Models;

/**
 * Transaction model of type NFTokenAcceptOffer.
 * PK: rAcct-27 SK: <INT> (Ledger index)
 */
class BTransactionNFTokenAcceptOffer extends BTransaction
{
  const TYPE = 27;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    if(isset($array['c']) && $array['c'] !== null) {
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    
    return $array;
  }

}