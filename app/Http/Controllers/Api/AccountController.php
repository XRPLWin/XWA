<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\AccountLoader;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Utilities\Search;
use XRPLWin\XRPL\Api\Methods\LedgerCurrent;
use Illuminate\Support\Facades\DB;

#use App\Statics\XRPL;
#use App\Statics\Account as StaticAccount;
#use App\Loaders\AccountLoader;
#use App\Models\AggregatDailyPayment;
#use Carbon\CarbonPeriod;

class AccountController extends Controller
{

  /*public function raw_info(string $account)
  {
    $info = XRPL::account_info($account);
    return response()->json($info);
  }

  public function raw_tx(string $account)
  {
    $txs = XRPL::account_tx($account);
    return response()->json($txs);
  }

  public function raw_lines(string $account)
  {
    $txs = XRPL::account_lines($account);
    return response()->json($txs);
  }*/

  ###############

  public function search(string $address, Request $request): JsonResponse
  {
    ini_set('memory_limit', '256M');
    validateXRPAddressOrFail($address);
    $search = new Search($address);
    
    //dd($request->input());
    $search->buildFromRequest($request); 
    $search->execute();
    if($search->hasErrors()) {
      return response()->json(['success' => false, 'error_code' => $search->getErrorCode(), 'errors' => $search->getErrors()],422);
    }


    $result =  ['success' => true];
    $result = array_merge($result, $search->result());


    $ttl = 5259487; //5 259 487 = 2 months
    
    //if end date is today we will set low ttl, since new data can come in at any time
    if($request->input('to') == \date('Y-m-d'))
      $ttl = 120; //2 mins

    return response()->json($result)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$ttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $ttl))
    ;
  }


  public function syncinfo(string $address): JsonResponse
  {
    $ttl = 5; //5 seconds (review this)

    validateXRPAddressOrFail($address);
  
    $r = [
      'synced' => false,   // bool
      'queued' => false, //bool
      'progress_current' => 0,
      'progress_total' => 0
    ];

    $acct = AccountLoader::getOrCreate($address);
    
    if($acct) {

      $r['progress_current'] = $acct->l;
      $r['progress_total'] = \App\Utilities\Ledger::current();

      if(!$acct->isSynced())
      {
        $queuedJobsCount = DB::table('jobs')->where('qtype_data',$acct->address)->where('attempts',0)->count();
        $r['queued'] = $queuedJobsCount?true:false;
        $r['synced'] = false;
      }


    }
   
    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$ttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $ttl));
  }


  public function info(string $address): JsonResponse
  {
    validateXRPAddressOrFail($address);
    
    $r = [
      'synced' => false,   // bool
      'sync_queued' => false, //bool
      'synced_info' => [
        'first' => null, //time of first transaciton
        'first_per_types' => [],   //times of first transaction per types
      ],
      'type' => 'normal', // normal|issuer|exchange
      'deleted' => false
    ];

    $acct = AccountLoader::getOrCreate($address);
   
    if(!$acct->isSynced())
    {
      $acct->sync(false,false,1500);
      $r['sync_queued'] = true;
      $r['synced'] = false;
    }

    $r['synced_info'] = $acct->getFirstTransactionAllInfo();

    $account_data = app(XRPLWinApiClient::class)->api('account_info')
        ->params([
            'account' => $address,
            'strict' => true
        ])
        ->send();
    
    
    if(!$account_data->isSuccess()) {
      if($account_data->result()->result->error == 'actNotFound') {
        $result = (object)[
          'Balance' => 0,
          'Flags' => null
        ];
        $r['deleted'] = true;
      }
    } else {
      $result = $account_data->finalResult();
    }
    
    
    $r['Balance'] = $result->Balance;
    $r['Flags'] = $result->Flags;
    if(isset($result->RegularKey))
      $r['RegularKey'] = $result->RegularKey;
    if(isset($result->Domain))
      $r['Domain'] = $result->Domain;
    if(isset($result->EmailHash))
      $r['EmailHash'] = $result->EmailHash;

    //get if this account is issuer or not by checking obligations
    if($acct->t === 1)
      $r['type'] = 'issuer';
    
    return response()->json($r);
  }

  /**
   * List of issued tokens
   * @return JsonResponse
   */
  public function issued(string $address): JsonResponse
  {
    validateXRPAddressOrFail($address);
    $issued = [];
    $acct = AccountLoader::getOrCreate($address);
    if(!$acct->isSynced())
      $acct->sync(false);

    if($acct->t === 1)
    {
      //get obligations
      $gateway_balances = app(XRPLWinApiClient::class)
        ->api('gateway_balances')
        ->params([
            'account' => $address,
            'strict' => true,
            'ledger_index' => 'validated',
        ])
        ->send()
        ->finalResult();

      if(isset($gateway_balances->obligations) && !empty($gateway_balances->obligations))
      {
        //has issued currencies
        foreach($gateway_balances->obligations as $k => $v) {
          $issued[] = [
            'currency_raw' => $k,
            'currency' => xrp_currency_to_symbol($k),
            'balance' => $v
          ];
        }
      }
    }


    return response()->json($issued);
  }

  /**
   * Returns JSON respose of all trustlines for $address
   TODO rewrite this, this must go with pagination (for issuers atleast)
   optionally pull trustline this account has reverse IOU, so if its issuer it can show trustlines to other tokens, this might be better fit.
   * @return JsonResponse
   */
  public function trustlines(string $address): JsonResponse
  {
    validateXRPAddressOrFail($address);
    $account_lines = app(XRPLWinApiClient::class)->api('account_lines')
    ->params([
        'account' => $address,
        'limit' => 5
    ]);

    $lines = [];

    $do = true;
    while($do) {
      try {
        $account_lines->send();
      } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
        // Handle errors
        $do = false;
        throw $e;
      }
    
      $txs = $account_lines->finalResult();
      foreach($txs as $tx) {
        $lines[] = $tx;
      }

      if($account_lines = $account_lines->next()) {
        //continuing to next page
      }
      else
        $do = false;
    }

    $trustlines = [];

    foreach($lines as $line) {
      $trustlines[] = [
        'account' => $line->account,
        'currency' => $line->currency,
        'symbol' => xrp_currency_to_symbol($line->currency),
        'balance' => $line->balance,
        'limit' => $line->limit
      ];
    }
    //dd($trustlines);
    return response()->json($trustlines);
  }

  /**
  * Chart data spending in XRP for account
  todo
  */
  public function chart_spending(string $account)
  {
    validateXRPAddressOrFail($account);
    $acct = new AccountLoader($account);
    if(!$acct->synced)
      return response()->json([]);

    $end = now();
    $start = now()->addDays(-330);

  //  dd($acct->account);
    $aggr = AggregatDailyPayment::select('amount','balance','date')
      ->where('account_id',$acct->account->id)
      ->whereBetween('date',[$start,$end])
      ->orderBy('date','asc')
      ->get();

    //Normalize ranges
    $range = new CarbonPeriod($start,'1 day',$end);
    //dd($range);

    $r = [];
    $balance = 0;
    //get starting balance
    $startingBalance = AggregatDailyPayment::select('balance')
      ->where('account_id',$acct->account->id)
      ->where('date', '<', $start)
      ->orderBy('date','desc')
      ->first();
    if($startingBalance)
      $balance = $startingBalance->balance;
    foreach($range as $day)
    {
      //dd($day);
      $data = $aggr->where('date',$day->startOfDay())->first();
      if(!$data) {
        $r[] = [
          $day->timestamp,
          0,
          $balance
        ];
      }
      else {
        $r[] = [
          $day->timestamp,
          $data->amount,
          $data->balance,
        ];
        $balance = $data->balance;
      }
    }

    return response()->json($r);
  }


  /*public function dev_analyze(string $account)
  {
    validateXRPAddressOrFail($account);
    $acct = new AccountLoader($account,false);
    if(!$acct->exists)
      dd('Does not exist locally');
    if(!$acct->synced)
      dd('Not synced');

    StaticAccount::analyzeData($acct->account);
    dd($acct);
  }*/

}
