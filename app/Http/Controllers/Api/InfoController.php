<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InfoController extends Controller
{
    public function info()
  {
    return response()->json([
      'version' => config('xwin.version'),
      'description' => 'XRPLWin Analyzer',
      'license' => 'MIT License',
      'copyright' => 'Copyright (c) 2022, XRPLWin (https://xrpl.win)',
      //'documentation' => 'TODO',
      //'release-notes' => 'TODO',
      'endpoints' => [
        [
          'action' => 'Get account info',
          'route' => '/v1/account/info/{account}',
          'method' => 'GET'
        ],
        [
          'action' => 'Trustline info for account',
          'route' => '/v1/account/trustlines/{account}',
          'method' => 'GET'
        ],
        [
          'action' => 'Chart data spending in XRP for account',
          'route' => '/v1/account/chart/spending/{account}',
          'method' => 'GET'
        ],
        [
          'action' => 'Get queue info',
          'route' => '/server/queue',
          'method' => 'GET',
          'example' => config('app.url').'/server/queue',
        ],
        [
          'action' => 'Get currency exchange rate',
          'route' => '/v1/currency_rates/{from}/{to}/{amount?}',
          'method' => 'GET',
          'example' => config('app.url').'/currency_rates/USD+rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq/XRP',
        ]
      ]
    ]);
  }
}
