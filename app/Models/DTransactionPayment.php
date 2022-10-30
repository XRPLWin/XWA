<?php

namespace App\Models;

/**
 * DynamoDB Transaction model of type Payment.
 * PK: rAcct-1 SK: <INT> (Ledger index)
 */
class DTransactionPayment extends DTransaction
{
  const TYPE = 1;

  public function toArray()
  {
    $array = ['type' => $this::TYPE];
    $array = \array_merge(parent::toArray(),$array);
    if(isset($array['i'])){
      //it is issued currency
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }
    return $array;
  }

}