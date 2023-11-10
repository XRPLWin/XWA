<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes Main
|--------------------------------------------------------------------------
|
| Main unchangeable API routes.
|
*/

Route::get('/', [App\Http\Controllers\Api\InfoController::class, 'info'])->name('info');
Route::get('/server/queue', [App\Http\Controllers\Api\ServerController::class, 'queue'])->name('server.queue');
Route::get('/server/syncstatus', [App\Http\Controllers\Api\ServerController::class, 'syncstatus'])->name('server.syncstatus');
Route::get('/static/avatar/{address}.webp', [App\Http\Controllers\StaticController::class, 'avatar_serve'])->name('static.avatar_serve');
# TEST
Route::get('/test', [App\Http\Controllers\MainController::class, 'test'])->name('test');
Route::get('/txtest', [App\Http\Controllers\MainController::class, 'txtest'])->name('txtest');


# Sample:

//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});