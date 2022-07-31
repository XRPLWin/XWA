<?php

namespace App\Statics;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class XRPL
{

  public static function ledger_current() : int
  {
    $client = new \GuzzleHttp\Client();
    $body = [
      'method' => 'ledger_current',
      /*'params' => [
          [
            'account' => $account,
            'strict' =>  false,
            'ledger_index' => 'current',
            'queue' =>  false
          ]
        ]*/
      ];
    $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_server_uri'), [
      'body' => json_encode( $body ),
      'headers' => [
        //'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $ret = \json_decode($response->getBody(),true);

    return $ret['result']['ledger_current_index'];
  }


  public static function account_info(string $account) : array
  {
    $client = new \GuzzleHttp\Client();
    $body = [
      'method' => 'account_info',
      'params' => [
          [
            'account' => $account,
            'strict' =>  false,
            'ledger_index' => 'current',
            'queue' =>  false
          ]
        ]
      ];
    $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_server_uri'), [
      'body' => json_encode( $body ),
      'headers' => [
        //'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $ret = \json_decode($response->getBody(),true);

    return $ret;
  }

  /**
  * Retrieves account transacitons, from marker or without it.
  * @param string $account - xrp account address
  * @param nullable array $marker - marker is offset
  * @see https://xrpl.org/account_tx.html
  */
  public static function account_tx(string $account, $ledger_index_min = -1, $ledger_index_max = -1, $marker = null) : array
  {
    $client = new \GuzzleHttp\Client();
    $body = [
      //'id' => 'xrpl.win_1',
      'method' => 'account_tx',
      'params' => [
        [
          'account' => $account,
          'ledger_index_min' => $ledger_index_min,
          'ledger_index_max' => $ledger_index_max,
          'binary' => false,
          'forward' => false,
          'limit' => 400,
          //'marker'
        ]
      ]
    ];
    //dd($body);

    if($marker)
    {
      $body['params'][0]['marker'] = $marker;
    }

    try {
      $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri'), [
        'http_errors' => false,
        'body' => json_encode( $body ),
        'headers' => [
          //'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
      ]);
      $status_code = $response->getStatusCode();
    } catch (\Throwable $e) {
      $status_code = 500;
    }

    if($status_code == 200) {
      $ret = \json_decode($response->getBody(),true);
      return ['success' => true, 'result' => $ret];
    }


      return ['success' => false];

  }



  /**
  * Executes gateway_balances command against XRPL.
  * @return array
  */
  public static function account_lines(string $account, $marker = null, $iteration = 1) : array
  {
    $client = new \GuzzleHttp\Client();
    $body = [
      'method' => 'account_lines',
      //'id' => 'xrpl.win_1',
      'params' => [
        [
          'account' => $account,
          'limit' => 400,
          //'marker'
          //"ledger_index" =>  "validated",
          //'hotwallet' => ['rKm4uWpg9tfwbVSeATv4KxDe6mpE9yPkgJ','ra7JkEzrgeKHdzKgo4EUUVBnxggY4z37kt'],
          //'ledger_hash' => '5DB01B7FFED6B67E6B0414DED11E051D2EE2B7619CE0EAA6286D67A3A4D5BDB3',
          //'strict' =>  false,
        ]
      ]
    ];

    if($marker)
    {
      $body['params'][0]['marker'] = $marker;
    }
    //dd(json_encode( $body ));
    $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_server_uri'), [
      'body' => json_encode( $body ),
      'headers' => [
        //'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $ret = \json_decode($response->getBody(),true);
    //dd($ret);
    if(isset($ret['result']['marker']) && $iteration <= 4)
    {
      //there are more than limit trustlines...
      $loadmore = self::account_lines($account,$ret['result']['marker'],($iteration+1));
      if(isset($loadmore['result']['status']) && $loadmore['result']['status'] == 'success')
      {
        //dd($loadmore);
        $loadmore['result']['lines'] = array_merge($ret['result']['lines'],$loadmore['result']['lines']);
        //if($iteration == 3) dd($loadmore,$marker,$lastmarker);
        $ret = $loadmore;
      }
    }

    return $ret;
  }

  /**
  * Executes gateway_balances command agains XRPL.
  * @return array
  */
  public static function gateway_balances(string $account) : array
  {
    $client = new \GuzzleHttp\Client();
    $body = [
      'method' => 'gateway_balances',
      //'id' => 'xrpl.win_1',
      'params' => [
        [
          'account' => $account,
          'ledger_index' =>  'validated',
          //'hotwallet' => ['rKm4uWpg9tfwbVSeATv4KxDe6mpE9yPkgJ','ra7JkEzrgeKHdzKgo4EUUVBnxggY4z37kt'],
          //'ledger_hash' => '5DB01B7FFED6B67E6B0414DED11E051D2EE2B7619CE0EAA6286D67A3A4D5BDB3',
          'strict' =>  false,
        ]
      ]
    ];
    //dd(json_encode( $body ));
    $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri'), [
      'body' => json_encode( $body ),
      'headers' => [
        //'Accept' => 'application/json',
        'Content-Type' => 'application/json',
      ],
    ]);
    $ret = \json_decode($response->getBody(),true);
  //  dd($ret);
    return $ret;
  }

  /**
  * TODO: error handling
  * @param array $params [ 'taker' => ..., 'taker_gets' => ..., ... ]
  * @see https://xrpl.org/book_offers.html#book_offers
  */
  public static function book_offers(array $params) : array
  {
      $client = new \GuzzleHttp\Client();
      $body = [
        'method' => 'book_offers',
        //'id' => 'xrpl.win_1',
        'params' => [
          $params
        ]
      ];
      //dd(json_encode( $body ));
      $response = $client->request('POST', config('xrpl.'.config('xrpl.net').'.rippled_server_uri'), [
        'body' => json_encode( $body ),
        'headers' => [
          //'Accept' => 'application/json',
          'Content-Type' => 'application/json',
        ],
      ]);
      $ret = \json_decode($response->getBody(),true);
      return $ret;
  }

}
