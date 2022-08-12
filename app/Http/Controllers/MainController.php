<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;
use \XRPLWin\XRPL\Client;

class MainController extends Controller
{
    public function front()
    {


      $account_tx = app(Client::class)->api('account_tx')
      ->params([
        'account' => 'rLvpVgX2tkB1FooMz5uQQJ6tCUKJX3Cwdq',
        'ledger_index' => 'current',
        'ledger_index_min' => 69492973, //Ledger index this account is scanned to.
        'ledger_index_max' => 69492985,
        'binary' => false,
        'forward' => true,
        'limit' => 10, //400
      ]);

      $account_tx->send();
      dd($account_tx, $account_tx->finalResult());
      $txs = $account_tx;







      $client = app(Client::class);
      $client2 = app(Client::class);
      dd($client,$client2);


      $client2 = new \XRPLWin\XRPL\Client([
        //'endpoint_reporting_uri' => 'https://test.com'
      ]);
      /*$ledgerCurrentPayload = $client->api('ledger_current')->send();
      if($ledgerCurrentPayload->isSuccess() )
        echo 'success';
      $final = $ledgerCurrentPayload->finalResult();*/



      //account_tx
      $account_tx = $client->api('account_tx')
        ->params([
          'account' => 'rLvpVgX2tkB1FooMz5uQQJ6tCUKJX3Cwdq',
          'limit' => 2
        ])
        ->send();

      if($nextRequest = $account_tx->next())
      {
        $account_tx2 = $nextRequest->send()->result();
        dd($account_tx->result(),$account_tx2);
      }

        dd('end',$account_tx->result());






      dd($client,  $ledgerCurrentPayload, $ledgerCurrentPayload->result(),$final);











      return '';
      $test = Account::all();

      dd($test);
      return response()->json(['mt' => microtime(true), 'tot' => microtime(true) - LARAVEL_START]);
    }
}
