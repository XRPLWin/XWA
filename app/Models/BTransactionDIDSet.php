<?php

namespace App\Models;

/**
 * Transaction model of type DIDSet.
 * PK: rAcct-56 SK: <INT> (Ledger index)
 */
class BTransactionDIDSet extends BTransaction
{
  const TYPE = 56;
  const TYPENAME = 'DIDSet';

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