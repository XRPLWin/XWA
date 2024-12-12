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
Route::get('/account/summary/{address}', [App\Http\Controllers\Api\AccountController::class, 'summary'])/*->middleware('varnish5min')*/->name('account.summary'); //high traffic supported, cached
Route::get('/account/syncinfo/{address}/{to?}', [App\Http\Controllers\Api\AccountController::class, 'syncinfo'])/*->middleware('varnish5min')*/->name('account.syncinfo');
//Route::get('/account/trustlines/{address}', [App\Http\Controllers\Api\AccountController::class, 'trustlines'])/*->middleware('varnish5min')*/->name('account.trustlines');
//Route::get('/account/issued/{address}', [App\Http\Controllers\Api\AccountController::class, 'issued'])/*->middleware('varnish5min')*/->name('account.issued');
//Route::get('/account/chart/spending/{account}', [App\Http\Controllers\Api\AccountController::class, 'chart_spending'])/*->middleware('varnish5min')*/->name('account.chart.spending');
Route::get('/account/search/{address}', [App\Http\Controllers\Api\AccountController::class, 'search'])/*->middleware('varnish5min')*/->name('account.search');
Route::get('/tokens', [App\Http\Controllers\Api\TokenController::class, 'all'])/*->middleware('varnish5min')*/->name('token.all');

Route::get('/ledger_index/{ymd}/first', [App\Http\Controllers\Api\LedgerController::class, 'ledger_index_first'])->name('ledger.ledger_index_first');
#Utilities
//Route::middleware(['varnish5min'])->group(function () {
  Route::get('/currency_rates/{from}/{to}/{amount?}', [App\Http\Controllers\Api\BookController::class, 'currency_rates'])->name('currency_rates');
//});

Route::get('/xahau/import/{from}/{to}/aggr', [App\Http\Controllers\Api\XahauController::class, 'import_aggr'])->name('xahau.import_aggr');
Route::get('/xahau/import/{date}/txs', [App\Http\Controllers\Api\XahauController::class, 'import_day_txs'])->name('xahau.import_day_txs');

Route::get('/oracle/USD', [App\Http\Controllers\Api\OracleController::class, 'usd'])->name('oracle.usd');
Route::get('/oracles', [App\Http\Controllers\Api\OracleController::class, 'oracles'])->name('oracles.index');
Route::get('/oracle-pairs', [App\Http\Controllers\Api\OracleController::class, 'oracle_pairs'])->name('oracles.pairs');

Route::get('/hooks/{filter}/{order}/{direction}', [App\Http\Controllers\Api\HookController::class, 'hooks'])->name('hooks');
Route::get('/hook/{hookhash}', [App\Http\Controllers\Api\HookController::class, 'hook'])->name('hook');
Route::get('/hook/{hookhash}/{hookctid}/transactions/{order}/{direction}', [App\Http\Controllers\Api\HookController::class, 'hook_transactions'])->name('hook.transactions');
Route::get('/hook/{hookhash}/{hookctid}/active-accounts/installed/{direction}', [App\Http\Controllers\Api\HookController::class, 'hook_active_accounts'])->name('hook.active_accounts');
Route::get('/hook/{hookhash}/{hookctid}/metrics/{from}/{to}', [App\Http\Controllers\Api\HookController::class, 'hook_metrics'])->name('hook.metrics');
Route::get('/hook-transactions/recent', [App\Http\Controllers\Api\HookController::class, 'hook_transactions_recent'])->name('hook.transactions_recent');
Route::get('/hookname/{hookhash}', [App\Http\Controllers\Api\HookController::class, 'hook_name'])->name('hook.name');

Route::get('/amm/pools/active/{order?}/{direction?}', [App\Http\Controllers\Api\AmmController::class, 'pools_active'])->name('amm.pools_active');


Route::get('/validators/dunl', [App\Http\Controllers\Api\ValidatorController::class, 'dunl'])->name('validators.dunl');
Route::get('/unlreport/{from}/{to?}', [App\Http\Controllers\Api\UnlReportController::class, 'report'])->name('unlreport.report');
Route::get('/validators/unl', [App\Http\Controllers\Api\UnlReportController::class, 'validators'])->name('unlreport.validators');
Route::get('/validators/unl/{validator}', [App\Http\Controllers\Api\UnlReportController::class, 'validator'])->name('unlreport.validator');
Route::get('/validators/unl/{validator}/monitor/status', [App\Http\Controllers\Api\UnlReportController::class, 'validator_monitor_status'])->name('unlreport.validator_monitor_status');
Route::get('/validators/unl/{validator}/reports/daily/{from}/{to}', [App\Http\Controllers\Api\UnlReportController::class, 'validator_reports_daily'])->name('unlreport.validator_reports_daily');

Route::get('/aggr/recent', [App\Http\Controllers\Api\AggrController::class, 'recent'])->name('aggr.recent');
Route::get('/nft/feed', [App\Http\Controllers\Api\NFTController::class, 'feed'])->name('nft.feed');