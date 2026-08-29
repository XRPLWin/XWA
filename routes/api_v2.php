<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Version 2
|--------------------------------------------------------------------------
|
| Versioned API routes, definition is located in RouteServiceProvider.
|
*/

#Account routes
Route::get('/account/summary/{address}', [App\Http\Controllers\Api\AccountController::class, 'summary'])/*->middleware('varnish5min')*/->name('account.summary'); //high traffic supported, cached
