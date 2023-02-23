<?php

namespace App\Models;

/**
 * Transaction model of type CheckCash.
 * PK: rAcct-17 SK: <INT> (Ledger index)
 */
class BTransactionCheckCash extends BTransaction
{
  const TYPE = 17;

  public function toFinalArray()
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