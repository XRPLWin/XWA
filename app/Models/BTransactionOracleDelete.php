<?php

namespace App\Models;

/**
 * Transaction model of type OracleDelete.
 * PK: rAcct-59 SK: <INT> (Ledger index)
 */
class BTransactionOracleDelete extends BTransaction
{
  const TYPE = 59;
  const TYPENAME = 'OracleDelete';

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