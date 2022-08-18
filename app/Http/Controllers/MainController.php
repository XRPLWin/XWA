<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use \XRPLWin\XRPL\Client;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;

class MainController extends Controller
{
    public function test()
    {
        $client = new Client([]);

        
        
        $lc = new LiquidityCheck([
            'from' => ['currency' => 'XRP'],
            'to' => ['currency' => 'USD', 'issuer' => 'rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq'],
            'amount' => 500,
            'limit' => 200
        ],[],$client);

        $liquidity =  $lc->get();

        dd($lc,$liquidity);
    }
}
