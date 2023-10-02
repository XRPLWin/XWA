<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class InfoController extends Controller
{
  public function info()
  {
    $endpoints = [
      [
        'action' => 'Get account summary',
        'route' => '/v1/account/summary/{address}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/account/summary/rWinEUKtN3BmYdDoGU6HZ7tTG54BeCAiz',
      ],
      [
        'action' => 'Get account info',
        'route' => '/v1/account/info/{address}',
        'method' => 'GET'
      ],
      [
        'action' => 'Trustline info for account',
        'route' => '/v1/account/trustlines/{address}',
        'method' => 'GET'
      ],
      [
        'action' => 'Issued currencies (obligations)',
        'route' => '/v1/account/issued/{address}',
        'method' => 'GET'
      ],
      [
        'action' => 'Chart data spending in XRP for account',
        'route' => '/v1/account/chart/spending/{address}',
        'method' => 'GET'
      ],
      [
        'action' => 'Search and filter account transactions and events (todo add missing type to pattern below)',
        'route' => '/v1/account/search/{address}?from=DD-MM-YYYY&to=DD-MM-YYYY[&dir=in|out][&st=Int32][&dt=Int32][&cp=rCounterpartyAccount]',
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
        'example' => config('app.url').'/v1/currency_rates/USD+rhub8VRN55s94qWKDv6jmDy1pUykJzF3wq/XRP',
      ],
      [
        'action' => 'Get XRP Price',
        'route' => '/v1/oracle/USD',
        'method' => 'GET',
        'example' => config('app.url').'/v1/oracle/USD',
      ]
    ];
    if(config('xrpl.'.config('xrpl.net').'.feature_unlreport')) {
      $endpoints[] = [
        'action' => 'Get UNLReports',
        'route' => '/v1/unlreport/{from}/{to?}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/unlreport/2023-09-01/2023-09-20',
      ];
    }
    return response()->json([
      'version' => config('xwa.version'),
      'description' => config('app.name'),
      'license' => 'MIT License',
      'copyright' => 'Copyright (c) '.\date('Y').', XRPLWin (https://xrpl.win)',
      //'documentation' => 'TODO',
      //'release-notes' => 'TODO',
      'endpoints' => $endpoints
    ]);
  }
}
