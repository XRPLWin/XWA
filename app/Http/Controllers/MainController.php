<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class MainController extends Controller
{
    public function front()
    {

      $client = new \XRPLWin\XRPL\Client([]);
      $ledger_current = $client->api('ledger_current')->execute();
      dd($client,  $ledger_current );
      return '';
      $test = Account::all();

      dd($test);
      return response()->json(['mt' => microtime(true), 'tot' => microtime(true) - LARAVEL_START]);
    }
}
