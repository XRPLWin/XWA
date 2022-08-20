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
            'to' => ['currency' => 'XRP'],
            'from' => ['currency' => '534F4C4F00000000000000000000000000000000', 'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'],

            //'to' => ['currency' => '5553444300000000000000000000000000000000', 'issuer' => 'rDsvn6aJG4YMQdHnuJtP9NLrFp18JYTJUf'],
            //'from' => ['currency' => 'ALV', 'issuer' => 'raEQc5krJ2rUXyi6fgmUAf63oAXmF7p6jp'],

            'amount' => 10,
            'limit' => 5
        ],[],$client);

        $liquidity =  $lc->get();

        return response()->json($liquidity);
        dd($liquidity);
    }
}
