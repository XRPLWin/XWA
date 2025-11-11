<?php

namespace App\Models;

/**
 * Transaction model of type Cron.
 * PK: rAcct-72 SK: <INT> (Ledger index)
 */
class BTransactionCron extends BTransaction
{
  const TYPE = 72;
  const TYPENAME = 'Cron';

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