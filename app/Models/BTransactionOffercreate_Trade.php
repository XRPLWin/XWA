<?php

namespace App\Models;

/**
 * Transaction model of OfferCreate sub-type.
 * PK: rAcct-8 SK: <INT> (Ledger index)
 */
class BTransactionOffercreate_Trade extends BTransaction
{
  const TYPE = 8;

  public function toFinalArray(): array
  {
    dd('todo toFinalArray BTransactionOffercreate');
    /*$array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    if(isset($array['c']) && $array['c'] !== null) {
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
      
    
    if(isset($array['c2']) && $array['c2'] !== null)
      $array['c2_formatted'] = xrp_currency_to_symbol($array['c2']);
    
    return $array;*/
  }

}