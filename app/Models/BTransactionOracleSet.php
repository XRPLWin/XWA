<?php

namespace App\Models;

/**
 * Transaction model of type OracleSet.
 * PK: rAcct-58 SK: <INT> (Ledger index)
 */
class BTransactionOracleSet extends BTransaction
{
  const TYPE = 58;
  const TYPENAME = 'OracleSet';

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