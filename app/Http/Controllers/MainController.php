<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use XRPLWin\XRPL\Client;
use XRPLWin\XRPLOrderbookReader\LiquidityCheck;

class MainController extends Controller
{
    public function test()
    {
        $client = app(Client::class);

        $lc = new LiquidityCheck([
            //'to' => ['currency' => 'XRP'],
            'from' => ['currency' => '534F4C4F00000000000000000000000000000000', 'issuer' => 'rsoLo2S1kiGeCcn6hCUXVrCpGMWLrRrLZz'],
            //'from' => ['currency' => 'MTA', 'issuer' => 'r95bSz69js5MrCoMdhejdGHvHyPRXumLTm'],
            'to' => ['currency' => 'XRP'],
            'amount' => 0.1
        ],[
            'maxBookLines' => 500,

        ],$client);

        $liquidity =  $lc->get();

        return response()->json($liquidity);
    }
}
