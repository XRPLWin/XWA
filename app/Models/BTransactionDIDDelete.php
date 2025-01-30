<?php

namespace App\Models;

/**
 * Transaction model of type DIDDelete.
 * PK: rAcct-57 SK: <INT> (Ledger index)
 */
class BTransactionDIDDelete extends BTransaction
{
  const TYPE = 57;
  const TYPENAME = 'DIDDelete';

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