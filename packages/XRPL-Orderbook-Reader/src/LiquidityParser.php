<?php declare(strict_types=1);

namespace XRPLWin\XRPLOrderbookReader;


class LiquidityParser
{
  /**
   * This methods takes XRPL Orderbook (book_offers) datasets and requested
   * volume to exchange and calculates the effective exchange rates based on the requested and available liquidity.
   * @param array $offers list of offers returned from XRPL
   * @param array $params parameters sent to 'book_offers' XRPL API
   * @param float|int $tradeAmount
   * @see https://github.com/XRPL-Labs/XRPL-Orderbook-Reader
   * @return array ['rate' => price, 'safe' => true, 'errors' => []]
   */
  public function parse(array $offers, array $params, $tradeAmount = 500) : array
  {

    if(!count($offers))
      return ['rate' => 0, 'safe' => true, 'errors' => []];

    $fromIsXrp = \strtoupper($params['taker_pays']['currency']) === 'XRP' ? true:false;
    $bookType = 'source'; //source or return


    if(is_string($offers[0]['TakerPays'])) // Taker pays XRP
      $bookType = $fromIsXrp ? 'source':'return';
    else {
      // Taker pays IOU
      if(
        \strtoupper($params['taker_pays']['currency']) === \strtoupper($offers[0]['TakerPays']['currency'])
      &&
        $params['taker_pays']['issuer'] === $offers[0]['TakerPays']['issuer']
      )
        $bookType = 'source';
      else
        $bookType = 'return';

    }

    $offers_filtered = [];
    foreach($offers as $offer)
    {
      //TODO
      //ignore if (a.TakerGetsFunded === undefined || (a.TakerGetsFunded && a.TakerGetsFunded.toNumber() > 0))
      //ignore if (a.TakerPaysFunded === undefined || (a.TakerPaysFunded && a.TakerPaysFunded.toNumber() > 0))
      $offers_filtered[] = $offer;
    }

    $i = 0;
    $reduced = \array_reduce($offers_filtered, function($a,$b) use ($i,$bookType,$tradeAmount) {

      $_PaysEffective = isset($b['taker_gets_funded']) ? $this->parseAmount($b['taker_gets_funded']) : $this->parseAmount($b['TakerGets']);
      $_GetsEffective = isset($b['taker_pays_funded']) ? $this->parseAmount($b['taker_pays_funded']) : $this->parseAmount($b['TakerPays']);

      $_GetsSum = $_GetsEffective + (($i > 0) ? $a[$i-1]['_I_Spend'] : 0);
      $_PaysSum = $_PaysEffective + (($i > 0) ? $a[$i-1]['_I_Get'] : 0);

      $_cmpField = ($bookType == 'source') ? '_I_Spend_Capped':'_I_Get_Capped';

      //Big number test
      //dd($tradeAmount +'10e-12' + 12);

      $_GetsSumCapped = ($i > 0 && $a[$i-1]['_cmpField'] >= $tradeAmount) ?
        $a[$i-1]['_cmpField']['_I_Spend_Capped']
        : $_GetsSum;
      $_PaysSumCapped = ($i > 0 && $a[$i-1]['_cmpField'] >= $tradeAmount) ?
        $a[$i-1]['_cmpField']['_I_Get_Capped']
        : $_PaysSum;

      $_CumulativeRate_Cap = null;
      $_Capped = $i > 0 ? $a[$i-1]['_Capped'] : false;

      if($bookType == 'source') {

        if(!$_Capped && $_GetsSumCapped !== null && $_GetsSumCapped > $tradeAmount) {
          $_GetsCap = 1 - (($_GetsSumCapped - $tradeAmount)/$_GetsSumCapped);
          /*dd(
            $_GetsCap,
            ($_GetsSumCapped - $tradeAmount),
            ($_GetsSumCapped - $tradeAmount)/$_GetsSumCapped,
            $_GetsCap
          );*/
          $_GetsSumCapped = $_GetsSumCapped * $_GetsCap;
          $_PaysSumCapped = $_PaysSumCapped * $_GetsCap;
          $_Capped = true;
        }
      } else { //$bookType == return
        if(!$_Capped && $_PaysSumCapped !== null && $_PaysSumCapped > $tradeAmount) {
          //todo test this
          $_PaysCap = 1 - (($_PaysSumCapped - $tradeAmount)/$_PaysSumCapped);
          //dd($_PaysCap);
          $_GetsSumCapped = $_GetsSumCapped * $_PaysCap;
          $_PaysSumCapped = $_PaysSumCapped * $_PaysCap;
          $_Capped = true;
        }
      }

      if($_PaysSumCapped > 0)
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
        $b['_I_Spend_Capped'] = $_GetsSumCapped;
        $b['_I_Get_Capped'] = $_PaysSumCapped;
        $b['_CumulativeRate_Cap'] = $_CumulativeRate_Cap;
        $b['_Capped'] = $_Capped;

        //TODO switch (direction) - not implemented yet
        if(true) //not reversed
        {
          if(isset($b['_ExchangeRate']))
            $b['_ExchangeRate'] = 1 / $b['_ExchangeRate'];
          if(isset($b['_CumulativeRate_Cap']))
            $b['_CumulativeRate_Cap'] = 1 / $b['_CumulativeRate_Cap'];
          if(isset($b['_CumulativeRate']))
            $b['_CumulativeRate'] = 1 / $b['_CumulativeRate'];
        }
      }
      else // One side of the offer is empty
      {
        $i++;
        return $a;
      }
      $i++;
      return $a+$b;

    },[]);

    if($reduced['_CumulativeRate_Cap'])
      $rate = $reduced['_CumulativeRate_Cap'];
    else
      $rate = $reduced['_CumulativeRate'];

    return ['rate' => $rate, 'safe' => true, 'errors' => []];
  }


  /**
  * Extracts amount from mixed $amount
  * @param mixed string|array
  * @return decimal|string|int
  */
  public static function parseAmount($amount)
  {
    if(empty($amount))
      return 0;

    if(is_array($amount))
      return $amount['value'];

    if(is_string($amount))
      return $amount/1000000;

    return 0;
  }
}
