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
        'route' => '/v1/account/search/{address}?from=YYYY-MM-DD&to=YYYY-MM-DD[&dir=in|out][&st=Int32][&dt=Int32][&cp=rCounterpartyAccount]',
        'method' => 'GET'
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
      ],
      
      [
        'action' => 'Get list of hooks',
        'route' => '/v1/hooks/{filter}/{order}/{direction}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/hooks/all/created/desc',
      ],
      /*[
        'action' => 'Get specific hook information',
        'route' => '/v1/hooks/active',
        'method' => 'GET',
        'example' => config('app.url').'/v1/hook/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77',
      ],*/
      [
        'action' => 'Get specific hook information',
        'route' => '/v1/hook/{hookhash}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/hook/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77',
      ],
      [
        'action' => 'Get transactions affected by a hook',
        'route' => '/v1/hook/{hookhash}/{hookctid}/transactions/{order}/{direction}?[params=TODO]',
        'method' => 'GET',
        'example' => config('app.url').'/v1/hook/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77/C00468D10000535A/transactions/created/desc', //todo gov hook sample
      ],
      [
        'action' => 'Get daily hook metrics (UTC dates)',
        'route' => '/v1/hook/{hookhash}/{hookctid}/metrics/{from}/{to}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/hook/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77/C00468D10000535A/metrics/2023-10-20/2023-11-20', //todo gov hook sample
      ],

    ];
    $endpoints[] = [
      'action' => 'Get UNL Validators',
      'route' => '/v1/validators/dunl',
      'method' => 'GET',
      'example' => config('app.url').'/v1/validators/dunl',
    ];
    if(config('xrpl.'.config('xrpl.net').'.feature_unlreport')) {
      $endpoints[] = [
        'action' => 'Get UNLReports',
        'route' => '/v1/unlreport/{from}/{to?}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/unlreport/2023-09-01/2023-09-20',
      ];
      $endpoints[] = [
        'action' => 'Get Validators list',
        'route' => '/v1/validators/unl',
        'method' => 'GET',
        'example' => config('app.url').'/v1/validators/unl',
      ];
      $endpoints[] = [
        'action' => 'Get Validator info',
        'route' => '/v1/validators/unl/{validator}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/validators/unl/ED3ABC6740983BFB13FFD9728EBCC365A2877877D368FC28990819522300C92A69',
      ];
      $endpoints[] = [
        'action' => 'Get aggregated Validator reports (per day)',
        'route' => '/v1/validators/unl/{validator}/reports/daily/{from}/{to}',
        'method' => 'GET',
        'example' => config('app.url').'/v1/validators/unl/ED3ABC6740983BFB13FFD9728EBCC365A2877877D368FC28990819522300C92A69/reports/daily/2023-10-01/2023-10-03',
      ];
    }


    if(config('xwa.sync_type') == 'account') {
      $endpoints[] = [
        'action' => 'Get account queue info',
        'route' => '/server/queue',
        'method' => 'GET',
        'example' => config('app.url').'/server/queue',
      ];
    } elseif(config('xwa.sync_type') == 'continuous') {
      $endpoints[] = [
        'action' => 'Get sync status',
        'route' => '/server/syncstatus',
        'method' => 'GET',
        'example' => config('app.url').'/server/syncstatus',
      ];
    }
    


    return response()->json([
      'version' => config('xwa.version'),
      'description' => config('app.name'),
      'license' => 'MIT License',
      'copyright' => 'Copyright (c) '.\date('Y').', XRPLWin (https://xrplwin.com)',
      'documentation' => 'https://docs.xrplwin.com',
      //'release-notes' => 'TODO',
      'endpoints' => $endpoints
    ]);
  }
}
