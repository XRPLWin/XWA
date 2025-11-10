<?php

namespace App\Models;

/**
 * Transaction model of type CronSet.
 * PK: rAcct-71 SK: <INT> (Ledger index)
 */
class BTransactionCronSet extends BTransaction
{
  const TYPE = 71;
  const TYPENAME = 'CronSet';

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