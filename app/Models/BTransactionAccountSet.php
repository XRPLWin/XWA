<?php

namespace App\Models;

/**
 * Transaction model of type OfferCreate.
 * PK: rAcct-10 SK: <INT> (Ledger index)
 */
class BTransactionAccountSet extends BTransaction
{
  const TYPE = 10;
  const TYPENAME = 'AccountSet';

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