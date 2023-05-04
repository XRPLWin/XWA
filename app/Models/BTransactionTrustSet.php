<?php

namespace App\Models;

/**
 * Transaction model of type TrustSet.
 * PK: rAcct-3 SK: <INT> (Ledger index)
 */
final class BTransactionTrustSet extends BTransaction
{
  const TYPE = 3;
  const TYPENAME = 'TrustSet';
  const CONTEXT_ADDTRUSTLINE = 'add';
  const CONTEXT_REMOVETRUSTLINE = 'remove';

  public function toFinalArray(): array
  {
    $array = [
      'type' => $this::TYPE,
      'typename' => $this::TYPENAME, //overriden below
      'context' => $this::CONTEXT_ADDTRUSTLINE
    ];
    $array = \array_merge(parent::toArray(),$array);
    if(isset($array['i']) && $array['i'] !== null && isset($array['c']) && $array['c'] !== null){
      //it is issued currency
      $array['c_formatted'] = xrp_currency_to_symbol($array['c']);
    }

    //CONTEXT
    if($array['a'] == '0' || empty($array['a']))
      $array['context'] = $this::CONTEXT_REMOVETRUSTLINE;
    
    $array['typename'] = $this::TYPENAME. ' ('.$array['context'].')';
    return $array;
  }
  
}