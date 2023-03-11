<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPLOrderbookReader\LiquidityParser;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;
#use App\Statics\XRPL;
#use App\Statics\Account as StaticAccount;
#use App\Models\Account;
#use App\Loaders\AccountLoader;
#use App\Utilities\LiquidityParser;

class BookController extends Controller
{

  /**
  * @param string $form 'XRP' or '<CURRENCY CODE OR HEX>+<ISSUER>'
  * @param string $to 'XRP' or '<CURRENCY CODE OR HEX>+<ISSUER>'
  * @param float|int $tradeAmount
  * @see https://github.com/XRPL-Labs/net-worth-xapp/blob/main/src/plugins/xapp-vue.js
  * @see https://github.com/XRPL-Labs/XRPL-Orderbook-Reader/blob/0378825be82cb21402a9a719c79bfc12a88e2f31/src/index.ts
  * @see https://github.com/XRPL-Labs/XRPL-Orderbook-Reader/blob/0378825be82cb21402a9a719c79bfc12a88e2f31/src/parser/LiquidityParser.ts#L54
  * @return \Illuminate\Http\Response JSON [ 'price' => x.xxx ]
  */
  public function currency_rates(string $from, string $to, int $amount = 500)
  {
    $r = [ 'price' => 0, 'from' => false, 'to' => false];

    if($from == $to)
      return response()->json($r);
     
    if($from == 'XRP')
      $_from = [ 'currency' => 'XRP' ];
    else {
      $_from = explode('+',$from);
      if(count($_from) != 2) abort(403);
        $_from = [ 'issuer' => $_from[1], 'currency' => $_from[0] ];
    }
    
    if($to == 'XRP')
      $_to = [ 'currency' => 'XRP' ];
    else {
      $_to = explode('+',$to);
      if(count($_to) != 2) abort(403);
      $_to = [ 'issuer' => $_to[1], 'currency' => $_to[0] ];
    }
    
    $params = [
      'from' => $_from,
      'to' => $_to,
      'amount' => $amount,
    ];

    $client = app(Client::class);

    $lc = new LiquidityCheck($params,
      [
        # Options:
        //'rates' => 'to',
        //'maxSpreadPercentage' => 4,
        //'maxSlippagePercentage' => 3,
        //'maxSlippagePercentageReverse' => 3,
        //'maxBookLines' => 500,
        'includeBookData' => true //default false
      ], $client);
    try {
      $Liquidity = $lc->get();
    } catch (\Throwable) {
      //Unable to connect to provided XRPL server...
      $Liquidity = [
        'rate' => null,
        'safe' => false,
        'errors' => ['CONNECT_ERROR']
      ];
    }
    //dd($Liquidity,$params);
   

    $r['price'] = $Liquidity['rate'];
    $r['from'] = $_from;
    $r['to'] = $_to;
    $r['safe'] = $Liquidity['safe'];
    $r['errors'] = $Liquidity['errors'];
    //$r['amount'] = $amount;

    return response()->json($r);
  }

  /**
  * Queries XRPLedger and returns orderbook, on fail returns empty array
  * @param array $params - arameters sent to 'book_offers' XRPL API
  * @return array list of offers directly from XRPL
  */
  private function currency_rates_fetch_book_offers(array $params) : array
  {
    //dd($params);
    $client = app(Client::class);

    $lc = new LiquidityCheck($params,
      [
        # Options:
        //'rates' => 'to',
        //'maxSpreadPercentage' => 4,
        //'maxSlippagePercentage' => 3,
        //'maxSlippagePercentageReverse' => 3,
        //'maxBookLines' => 500,
        'includeBookData' => true //default false
      ], $client);
    
    try {
    $Liquidity = $lc->get();
    } catch (\Throwable) {
      //Unable to connect to provided XRPL server...
    $Liquidity = [
        'rate' => null,
        'safe' => false,
        'errors' => ['CONNECT_ERROR']
    ];
    }
      
      dd($Liquidity); 

    $orderbookResponse = XRPL::book_offers($params);

    if(isset($orderbookResponse['result']['status']) && $orderbookResponse['result']['status'] == 'success')
    {
      $offers = $orderbookResponse['result']['offers'];
      if(!is_array($offers))
        return [];

      if(count($offers) == 0)
        return [];

      return $offers;
    }
    return [];
  }

}
