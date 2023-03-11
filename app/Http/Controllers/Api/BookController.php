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
  * @return \Illuminate\Http\Response JSON
  */
  public function currency_rates(string $from, string $to, float|int $amount = 500)
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

    $r['price'] = $Liquidity['rate'];
    $r['from'] = $_from;
    $r['to'] = $_to;
    $r['safe'] = $Liquidity['safe'];
    $r['errors'] = $Liquidity['errors'];

    return response()->json($r);
  }

}
