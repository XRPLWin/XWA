<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Account;

class MainController extends Controller
{
    public function front()
    {
      $test = Account::all();

      dd($test);
      return response()->json(['mt' => microtime(true), 'tot' => microtime(true) - LARAVEL_START]);
    }
}
