<?php

namespace App\Models;

/**
 * Transaction model of type OfferCreate.
 * PK: rAcct-11 SK: <INT> (Ledger index)
 */
class BTransactionSetRegularKey extends BTransaction
{
  const TYPE = 11;

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    if(isset($array['c']) && $array['c'] !== null) {
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    
    return $array;
  }

}