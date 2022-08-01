<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class MainController extends Controller
{
    public function front()
    {

      $client = new \XRPLWin\XRPL\Client;
      dd($client);
      return '';
      $test = Account::all();

      dd($test);
      return response()->json(['mt' => microtime(true), 'tot' => microtime(true) - LARAVEL_START]);
    }
}
