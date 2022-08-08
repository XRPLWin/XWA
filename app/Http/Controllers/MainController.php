<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class MainController extends Controller
{
    public function front()
    {

      $client = new \XRPLWin\XRPL\Client([
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
