<?php

namespace App\Models;

/**
 * Transaction model of type AMMDeposit.
 * PK: rAcct-52 SK: <INT> (Ledger index)
 */
class BTransactionAMMDeposit extends BTransaction
{
  const TYPE = 52;
  const TYPENAME = 'AMMDeposit';

  public function toFinalArray(): array
  {
    $array = ['type' => $this::TYPE, 'typename' => $this::TYPENAME];
    $array = \array_merge(parent::toFinalArray(),$array);
    
    if(isset($array['c']) && $array['c'] !== null)
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    
    if(isset($array['c2']) && $array['c2'] !== null)
      $array['c2_formatted'] = xrp_currency_to_symbol($array['c2']);

    if(isset($array['c3']) && $array['c3'] !== null)
      $array['c3_formatted'] = xrp_currency_to_symbol($array['c3']);
    
    return $array;
  }

}