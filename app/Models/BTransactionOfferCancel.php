<?php

namespace App\Models;

/**
 * Transaction model of type OfferCancel.
 * PK: rAcct-9 SK: <INT> (Ledger index)
 */
class BTransactionOfferCancel extends BTransaction
{
  const TYPE = 9;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    if(isset($array['c']) && $array['c'] !== null) {
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    
    if(isset($array['c2']) && $array['c2'] !== null)
      $array['c2_formatted'] = xrp_currency_to_symbol($array['c2']);
    
    return $array;
  }

}