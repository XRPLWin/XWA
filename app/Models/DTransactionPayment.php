<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-1 SK: <INT> (Ledger index)
 */
class DTransactionPayment extends DTransaction
{
  const TYPE = 1;

  public function toFinalArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toArray(),$array);
    if(isset($array['c']))
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    
    if(isset($array['c2']))
      $array['c2_formatted'] = xrp_currency_to_symbol($array['c2']);
    
    return $array;
  }

}