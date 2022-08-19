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
            'to' => ['currency' => '534F4C4F00000000000000000000000000000000', 'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'],
            'amount' => 500,
            'limit' => 100
        ],[],$client);

        $liquidity =  $lc->get();

        dd($lc,$liquidity);
    }
}
