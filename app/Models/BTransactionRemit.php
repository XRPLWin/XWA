<?php

namespace App\Models;

/**
 * Transaction model of type Remit.
 * PK: rAcct-50
 */
class BTransactionRemit extends BTransaction
{
  const TYPE = 50;
  const TYPENAME = 'Remit';

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

    //Expand cx?
    if(isset($array['ax'])) {
      $i = 4;
      foreach($array['ax'] as $index => $x) {
        $array['a'.$i] = $array['ax'][$index];
        if($array['ix'][$index] !== null) $array['i'.$i] = $array['ix'][$index];
        if($array['cx'][$index] !== null) {
          $array['c'.$i] = $array['cx'][$index];
          $array['c'.$i.'_formatted'] = xrp_currency_to_symbol($array['cx'][$index]);
        }
        $i++;
      }

      unset($array['ax']);
      unset($array['ix']);
      unset($array['cx']);
      
    }

    return $array;
  }

}