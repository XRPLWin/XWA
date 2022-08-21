<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class LiquidityParser
{
  const PRECISION = 20;
  const ROUNDING_MODE = RoundingMode::HALF_UP;
  /**
   * This methods takes XRPL Orderbook (book_offers) datasets and requested
   * volume to exchange and calculates the effective exchange rates based on 
   * the requested and available liquidity.
   * @param array $offers list of offers returned from XRPL book_offers API
   * @param array $from
   * @param array $to
   * @param mixed $amount int|decimal|float|string - number
   * @param string $rates = 'from'|'to'
   * @see https://github.com/XRPL-Labs/XRPL-Orderbook-Reader/blob/master/src/parser/LiquidityParser.ts
   * @return array
   */
  public static function parse(array $offers, array $from, array $to, mixed $amount, string $rates = 'to') : array
  {
    if(!count($offers))
      return [];

    if($from === $to)
      return [];
    
    $fromIsXrp = \strtoupper($from['currency']) === 'XRP' ? true:false; 
    $bookType = 'source'; //source|return

    if(is_string($offers[0]->TakerPays)) // Taker pays XRP
      $bookType = $fromIsXrp ? 'source':'return';
    else {
      // Taker pays IOU
      if(
        \strtoupper($from['currency']) === \strtoupper($offers[0]->TakerPays->currency)
      &&
         $from['issuer'] === $offers[0]->TakerPays->issuer
      )
        $bookType = 'source';
      else
        $bookType = 'return';

    }

    $offers_filtered = [];
    
    foreach($offers as $offer)
    {
      if(
        ( !isset($offer->taker_gets_funded) || (isset($offer->taker_gets_funded) && self::parseAmount($offer->taker_gets_funded)->isGreaterThan(0)) )
        &&
        ( !isset($offer->taker_pays_funded) || (isset($offer->taker_pays_funded) && self::parseAmount($offer->taker_pays_funded)->isGreaterThan(0)) )
      ) {
        $offers_filtered[] = $offer;
      }
    }

    
    $amount = BigDecimal::of($amount);//dd($amount);
    $i = 0;
    //$reduceFiltered = [];
    /**
     * @param $a array of offers
     * @param $b object one offer
     */
    $reduced = \array_reduce($offers_filtered, function($a,$b) use (  $bookType, $amount, $rates, &$i ) {

      $b = (array)$b;

      /** @var \Brick\Math\BigDecimal */
      $_PaysEffective = isset($b['taker_gets_funded']) ? self::parseAmount($b['taker_gets_funded']) : self::parseAmount($b['TakerGets']);
      /** @var \Brick\Math\BigDecimal */
      $_GetsEffective = isset($b['taker_pays_funded']) ? self::parseAmount($b['taker_pays_funded']) : self::parseAmount($b['TakerPays']);

      /** @var \Brick\Math\BigDecimal */
      $_GetsSum = $_GetsEffective->plus( (($i > 0) ? $a[$i-1]['_I_Spend'] : BigDecimal::of(0)) );
      /** @var \Brick\Math\BigDecimal */
      $_PaysSum = $_PaysEffective->plus( (($i > 0) ? $a[$i-1]['_I_Get']   : BigDecimal::of(0)) );

      $_cmpField = ($bookType == 'source') ? '_I_Spend_Capped':'_I_Get_Capped';

     
      /** @var \Brick\Math\BigDecimal|null */
      $_GetsSumCapped = ($i > 0 && $a[$i-1][$_cmpField] !== null && $a[$i-1][$_cmpField]->isGreaterThanOrEqualTo($amount) )
        ? $a[$i-1]['_I_Spend_Capped']
        : $_GetsSum;
        
      /** @var \Brick\Math\BigDecimal|null */
      $_PaysSumCapped = ($i > 0 && $a[$i-1][$_cmpField] !== null && $a[$i-1][$_cmpField]->isGreaterThanOrEqualTo($amount))
        ? $a[$i-1]['_I_Get_Capped']
        : $_PaysSum;
      
      $_CumulativeRate_Cap = null;

      /** @var bool */
      $_Capped = ($i > 0) ? $a[$i-1]['_Capped'] : false;

      if($bookType == 'source') {
        if($_Capped === false && $_GetsSumCapped !== null && $_GetsSumCapped->isGreaterThan($amount)) {
          
          $_GetsCap = BigDecimal::of(1)->minus( $_GetsSumCapped->minus($amount)->dividedBy($_GetsSumCapped,self::PRECISION,self::ROUNDING_MODE) );
          $_GetsSumCapped = $_GetsSumCapped->multipliedBy($_GetsCap);
          $_PaysSumCapped = $_PaysSumCapped->multipliedBy($_GetsCap);
          $_Capped = true;

        }
      } else { //$bookType == 'return'
        if($_Capped === false && $_PaysSumCapped !== null && $_PaysSumCapped->isGreaterThan($amount)) {

          $_PaysCap = BigDecimal::of(1)->minus( $_PaysSumCapped->minus($amount)->dividedBy($_PaysSumCapped,self::PRECISION,self::ROUNDING_MODE) );
          $_GetsSumCapped = $_GetsSumCapped->multipliedBy($_PaysCap);
          $_PaysSumCapped = $_PaysSumCapped->multipliedBy($_PaysCap);
          $_Capped = true;

        }
      }

      if($_Capped !== null && $_PaysSumCapped !== null && $_PaysSumCapped->isGreaterThan(0)) {
        $_CumulativeRate_Cap = $_GetsSumCapped->dividedBy($_PaysSumCapped,self::PRECISION,self::ROUNDING_MODE);
      }

      if($i > 0 && ( $a[$i-1]['_Capped'] === true || $a[$i-1]['_Capped'] === null )) {
        $_GetsSumCapped = null;
        $_PaysSumCapped = null;
        $_CumulativeRate_Cap = null;
        $_Capped = null;
      }

      if($_GetsSum->isGreaterThan(0) && $_PaysSum->isGreaterThan(0)) {
        $b['_I_Spend'] = $_GetsSum;
        $b['_I_Get'] = $_PaysSum;
        $b['_ExchangeRate']       = ($_PaysEffective->isEqualTo(0)) ? null : $_GetsEffective->dividedBy($_PaysEffective,self::PRECISION,self::ROUNDING_MODE); //BigDecimal
        $b['_CumulativeRate']     = $_GetsSum->dividedBy($_PaysSum,self::PRECISION,self::ROUNDING_MODE);  // BigDecimal
        $b['_I_Spend_Capped']     = $_GetsSumCapped;                  // null|BigDecimal
        $b['_I_Get_Capped']       = $_PaysSumCapped;                  // null|BigDecimal
        $b['_CumulativeRate_Cap'] = $_CumulativeRate_Cap;             // null|BigDecimal
        $b['_Capped']             = $_Capped;                         // null|bool

        //Reverse rate output
        if($rates == 'to')
        {
          if(isset($b['_ExchangeRate']) && $b['_ExchangeRate'] !== null)
            $b['_ExchangeRate'] = BigDecimal::of(1)->dividedBy($b['_ExchangeRate'],self::PRECISION,self::ROUNDING_MODE);
          if(isset($b['_CumulativeRate_Cap']))
            $b['_CumulativeRate_Cap'] = BigDecimal::of(1)->dividedBy($b['_CumulativeRate_Cap'],self::PRECISION,self::ROUNDING_MODE);
          if(isset($b['_CumulativeRate']))
            $b['_CumulativeRate'] = BigDecimal::of(1)->dividedBy($b['_CumulativeRate'],self::PRECISION,self::ROUNDING_MODE);
        }
      } else { // One side of the offer is empty
        $i++;
        return $a;
      }
      $i++;

      array_push($a,$b); //append $b item to end of $a array collection
      return $a;

    },[]);
    
    # Filter $reduced orders
    $reducedFiltered = [];
    foreach($reduced as $v) {
      if(empty($v)) continue;
      if(!isset($v['_Capped'])) continue;
      if($v['_Capped'] === null) continue;
      if(!isset($v['_ExchangeRate'])) continue;
      if($v['_ExchangeRate'] === null) continue;
      $reducedFiltered[] = $v;
    }
    
    return $reducedFiltered;
  }


  /**
  * Extracts amount from mixed $amount
  * @param mixed string|array|object
  * @return BigDecimal
  */
  public static function parseAmount(string|array|object $amount): BigDecimal
  {
    if(empty($amount))
      return BigDecimal::of(0);

    if(is_object($amount))
      return BigDecimal::of($amount->value);

    if(is_array($amount))
      return BigDecimal::of($amount['value']);

    if(is_string($amount)) {
      $number = BigDecimal::of($amount)->exactlyDividedBy(1000000);
      return $number;
    }
      
    return BigDecimal::of(0);
  }
}
