<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-1 SK: <INT> (Ledger index)
 */
final class DTransactionTrustset extends DTransaction
{
  const TYPE = 3;

  public function toArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge($array,parent::toArray());
    if(isset($array['i'])){
      //it is issued currency
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    return $array;
  }
  
}