<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Version 1
|--------------------------------------------------------------------------
|
| Versioned API routes, definition is located in RouteServiceProvider.
|
*/

#Account routes
Route::get('/account/info/{address}', [App\Http\Controllers\Api\AccountController::class, 'info'])/*->middleware('varnish5min')*/->name('account.info');
Route::get('/account/syncinfo/{address}/{to?}', [App\Http\Controllers\Api\AccountController::class, 'syncinfo'])/*->middleware('varnish5min')*/->name('account.syncinfo');
Route::get('/account/trustlines/{address}', [App\Http\Controllers\Api\AccountController::class, 'trustlines'])/*->middleware('varnish5min')*/->name('account.trustlines');
Route::get('/account/issued/{address}', [App\Http\Controllers\Api\AccountController::class, 'issued'])/*->middleware('varnish5min')*/->name('account.issued');
//Route::get('/account/chart/spending/{account}', [App\Http\Controllers\Api\AccountController::class, 'chart_spending'])/*->middleware('varnish5min')*/->name('account.chart.spending');

Route::get('/account/search/{address}', [App\Http\Controllers\Api\AccountController::class, 'search'])/*->middleware('varnish5min')*/->name('account.search');

#Utilities
//Route::middleware(['varnish5min'])->group(function () {
  Route::get('/currency_rates/{from}/{to}/{amount?}', [App\Http\Controllers\Api\BookController::class, 'currency_rates'])->name('currency_rates');
//});

Route::get('/oracle/USD', [App\Http\Controllers\Api\OracleController::class, 'usd'])->name('oracle.usd');