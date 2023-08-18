<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\AccountLoader;
use App\Utilities\Account as XRPLAccountInfo;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Utilities\Search;
use XRPLWin\XRPL\Api\Methods\LedgerCurrent;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
    
    #$_rand = rand(1,9999999999);Log::build(['driver' => 'single','path' => storage_path('logs/bq.log')])->info('# Start '.$_rand);
    
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
    $httpttl = 172800; //172 800 = 2 days
    
    //if end date is today we will set low ttl, since new data can come in at any time
    if($request->input('to') == \date('Y-m-d')) {
      $ttl = 300; //5 mins
      $httpttl = 300; //5 mins
    }
      

    #Log::build(['driver' => 'single','path' => storage_path('logs/bq.log')])->info('# End '.$_rand);
    return response()->json($result)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }


  public function syncinfo(string $address, string $to_datetime = null): JsonResponse
  {
    validateXRPAddressOrFail($address);

    if($to_datetime) {
      $validator = Validator::make(['to_datetime' => $to_datetime], [
        'to_datetime' => 'required|date',
      ]);
      if ($validator->fails()) {
        abort(422,'Invalid date format');
      }
      $ttl = 3600; //3600  = 1h
      $referenceTime = Carbon::createFromFormat('Y-m-d',$to_datetime)->startOfDay();
      if($referenceTime->isFuture())
        $referenceTime = now();
      elseif($referenceTime->isToday())
        $referenceTime = now();
      else
        $referenceTime->endOfDay();
      //  dd($referenceTime);
    } else { //latest time requested
      $ttl = 5; //5 seconds for latest
      $referenceTime = now();
    }

    $r = [
      'synced' => false,  //bool
      'queued' => false,  //bool
      'no_in_queue' => 0,  //int
      'running' => false, //bool
      'progress_current' => 0, //unix timestamp - offset
      'progress_current_time' => null, //current time
      'progress_total' => $referenceTime->format('U'),
    ];

    /** @var \App\Models\BAccount */
    $acct = AccountLoader::getOrCreate($address);
    
    if($acct) {
      $firstTxInfo = $acct->getFirstTransactionAllInfo();
      $offset = $firstTxInfo['first'] ? $firstTxInfo['first']:0;
      
      $r['progress_current_time'] = $acct->lt;
      $r['progress_current'] = $acct->lt->timestamp - $offset;
      if($r['progress_current'] < 0) $r['progress_current'] = 0;
      $r['progress_total'] = $r['progress_total'] - $offset;
      $r['synced'] = true;

      if(!$acct->isSynced(10,$referenceTime)) {
        $ttl = 5; //5 seconds for latest
        $queuedJob = DB::table('jobs')->select('id','queue','started_at')->where('qtype_data',$acct->address)->first();

        if($queuedJob) {
          $r['queued'] = true;
          if($queuedJob->started_at !== null) $r['running'] = true;
          $r['no_in_queue'] = DB::table('jobs')
            ->where('queue',$queuedJob->queue)
            ->where('id','<',$queuedJob->id)
            ->count();
        }

        $r['synced'] = false;
        if(!$r['queued']) {
          $acct->sync(false,false,1500);
        }
      }
    }
   
    /*if($r['progress_total'] == 0 || $r['progress_current'] == 0)
      $r['progress_percent'] = 0;
    else
      $r['progress_percent'] = ceil(($r['progress_total']-$r['progress_current'])/$r['progress_current'] * 100);
    if( $r['progress_percent'] > 100)  $r['progress_percent'] = 100;*/

    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$ttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $ttl));
  }

  /**
   * Fast account summary, does not use BQ, connects to XRPL.
   * For high traffic.
   * Cached
   * @return JsonResponse
   */
  public function summary(string $address): JsonResponse
  {
    validateXRPAddressOrFail($address);

    $AInfoInstance = new XRPLAccountInfo($address);
    $AInfo = $AInfoInstance->get();
    if($AInfo === null)
      abort(404); //todo cooldown this somehow

    $ttl = 86400; //86 400 = 1 day
    $httpttl = 86400; //86 400 = 1 day

    if(!$AInfoInstance->isComplete()) {
      //something is missing, lower http cache, so script can re-run soon
      $ttl = 1800; //30 min
      $httpttl = 1800; //30 min
    }
    
    #Log::build(['driver' => 'single','path' => storage_path('logs/bq.log')])->info('# End '.$_rand);
    return response()->json($AInfo)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
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
      'type' => 'normal', // normal|issuer|exchange - we have this info in summary (todo)
      'deleted' => false
    ];

    $acct = AccountLoader::getOrCreate($address);

    if(!$acct->isSynced(3))
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
