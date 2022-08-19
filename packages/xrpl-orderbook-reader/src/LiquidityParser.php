<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;


class LiquidityParser
{
  /**
   * This methods takes XRPL Orderbook (book_offers) datasets and requested
   * volume to exchange and calculates the effective exchange rates based on 
   * the requested and available liquidity.
   * @param array $offers list of offers returned from XRPL book_offers API
   * @param array $from
   * @param array $to
   * @param mixed $amount int|decimal|float|string - number
   * @see https://github.com/XRPL-Labs/XRPL-Orderbook-Reader/blob/master/src/parser/LiquidityParser.ts
   * @return array
   */
  public static function parse(array $offers, array $from, array $to, mixed $amount) : array
  {
    if(!count($offers))
      return 0;

    if($from === $to)
      return 0;
    
    $fromIsXrp = \strtoupper($from['currency']) === 'XRP' ? true:false; 
    $bookType = 'source'; //source or return

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
    //dd($bookType);

    $offers_filtered = [];
    foreach($offers as $offer)
    {
      //ignore if (a.TakerGetsFunded === undefined || (a.TakerGetsFunded && a.TakerGetsFunded.toNumber() > 0))
      //ignore if (a.TakerPaysFunded === undefined || (a.TakerPaysFunded && a.TakerPaysFunded.toNumber() > 0))
      if(
        ( !isset($offer->taker_gets_funded) || (isset($offer->taker_gets_funded) && self::parseAmount($offer->taker_gets_funded) > 0) )
        &&
        ( !isset($offer->taker_pays_funded) || (isset($offer->taker_pays_funded) && self::parseAmount($offer->taker_pays_funded) > 0) )
      ) {
        $offers_filtered[] = $offer;
      }
      //else dd($offer,'non funded',self::parseAmount($offer->taker_gets_funded),self::parseAmount($offer->taker_pays_funded));
      
    }

    //dd($offers_filtered,$offers);

    $i = 0;
    //$reduceFiltered = [];
    /**
     * @param $a array of offers
     * @param $b object one offer
     */
    $reduced = \array_reduce($offers_filtered, function($a,$b) use (  $bookType, $amount, &$i, &$reduceFiltered ) {
      //$a = (array)$a;
      $b = (array)$b;
      
      //if(!empty($a)) dd($a,$b);

      $_PaysEffective = isset($b['taker_gets_funded']) ? self::parseAmount($b['taker_gets_funded']) : self::parseAmount($b['TakerGets']);
      $_GetsEffective = isset($b['taker_pays_funded']) ? self::parseAmount($b['taker_pays_funded']) : self::parseAmount($b['TakerPays']);

      $_GetsSum = $_GetsEffective + (($i > 0) ? $a[$i-1]['_I_Spend'] : 0);
      $_PaysSum = $_PaysEffective + (($i > 0) ? $a[$i-1]['_I_Get'] : 0);

      $_cmpField = ($bookType == 'source') ? '_I_Spend_Capped':'_I_Get_Capped';
      //Big number test
      //dd($amount +'10e-12' + 12);

      $_GetsSumCapped = ($i > 0 && $a[$i-1][$_cmpField] >= $amount)
        ? $a[$i-1]['_I_Spend_Capped']
        : $_GetsSum;

      $_PaysSumCapped = ($i > 0 && $a[$i-1][$_cmpField] >= $amount) 
        ? $a[$i-1]['_I_Get_Capped']
        : $_PaysSum;

      $_CumulativeRate_Cap = null;
      $_Capped = ($i > 0) ? $a[$i-1]['_Capped'] : false;

      if($bookType == 'source') {
        if($_Capped === false && $_GetsSumCapped !== null && $_GetsSumCapped > $amount) {
          
          $_GetsCap = 1 - (($_GetsSumCapped - $amount)/$_GetsSumCapped);
          /*dd(
            $_GetsCap,
            ($_GetsSumCapped - $amount),
            ($_GetsSumCapped - $amount)/$_GetsSumCapped,
            $_GetsCap
          );*/
          $_GetsSumCapped = $_GetsSumCapped * $_GetsCap;
          $_PaysSumCapped = $_PaysSumCapped * $_GetsCap;
          $_Capped = true;
        }
      } else { //$bookType == 'return'
        if($_Capped === false && $_PaysSumCapped !== null && $_PaysSumCapped > $amount) {
          //todo test this
          $_PaysCap = 1 - (($_PaysSumCapped - $amount)/$_PaysSumCapped);

          $_GetsSumCapped = $_GetsSumCapped * $_PaysCap;
          $_PaysSumCapped = $_PaysSumCapped * $_PaysCap;
          $_Capped = true;
        }
      }

      if($_Capped !== null && $_PaysSumCapped > 0)
        $_CumulativeRate_Cap = $_GetsSumCapped/$_PaysSumCapped;

      if($i > 0 && ( $a[$i-1]['_Capped'] === true || $a[$i-1]['_Capped'] === null )) {
        $_GetsSumCapped = null;
        $_PaysSumCapped = null;
        $_CumulativeRate_Cap = null;
        $_Capped = null;
      }

      //dd($_GetsEffective,$_PaysEffective,($_GetsEffective / $_PaysEffective));

      if($_GetsSum > 0 && $_PaysSum > 0) {
        $b['_I_Spend'] = $_GetsSum;
        $b['_I_Get'] = $_PaysSum;
        $b['_ExchangeRate'] = ($_PaysEffective == 0) ? null : ($_GetsEffective / $_PaysEffective);
        $b['_CumulativeRate'] = $_GetsSum / $_PaysSum;
        $b['_I_Spend_Capped'] = $_GetsSumCapped;          // null|number
        $b['_I_Get_Capped'] = $_PaysSumCapped;            // null|number
        $b['_CumulativeRate_Cap'] = $_CumulativeRate_Cap; // null|number
        $b['_Capped'] = $_Capped;                         // null|bool

        //Following commented functionality is not implemented.
        //if (((_b = (_a = ParserData.options) === null || _a === void 0 ? void 0 : _a.rates) === null || _b === void 0 ? void 0 : _b.toLowerCase().trim()) === 'to') {
        //if(true) //not reversed, if RatesInCurrency.to = 'to' then true: (not used)
        //{
          if(isset($b['_ExchangeRate']) && $b['_ExchangeRate'] !== null)
            $b['_ExchangeRate'] = 1 / $b['_ExchangeRate'];
          if(isset($b['_CumulativeRate_Cap']))
            $b['_CumulativeRate_Cap'] = 1 / $b['_CumulativeRate_Cap'];
          if(isset($b['_CumulativeRate']))
            $b['_CumulativeRate'] = 1 / $b['_CumulativeRate'];
        //}
      }
      else // One side of the offer is empty
      {
        $i++;
        return $a;
      }
      $i++;

      array_push($a,$b); //append $b array item to end of $a array collection
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

    /*if($reduced['_CumulativeRate_Cap'])
      $rate = $reduced['_CumulativeRate_Cap'];
    else
      $rate = $reduced['_CumulativeRate'];

      dd($reduced,$rate);
    return $rate;*/
  }


  /**
  * Extracts amount from mixed $amount
  * @param mixed string|array|object
  * @return float|string|int
  */
  public static function parseAmount($amount)
  {
    if(empty($amount))
      return 0;

    if(is_object($amount))
      return $amount->value;

    if(is_array($amount))
      return $amount['value'];

    if(is_string($amount))
      return $amount/1000000;

    return 0;
  }
}
