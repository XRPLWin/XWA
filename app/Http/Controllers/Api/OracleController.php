<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
use App\Models\Oracle;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class OracleController extends Controller
{

  /**
   * Legacy oracle (will work on xahau due to XRPLLabs)
   * @see https://github.com/XRPL-Labs/XRPL-Persist-Price-Oracle/blob/main/README.md
   */
  public function usd()
  {
    $account_lines = app(XRPLWinApiClient::class)->api('account_lines')
      ->params([
          'account' => 'rXUMMaPpZqPutoRszR29jtC8amWq3APkx',
          'limit' => 1
      ]);

    try {
      $account_lines->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
      // Handle errors
      throw $e;
    }

    $lines = (array)$account_lines->finalResult();

    $ttl = 300; //5 mins
    $httpttl = 300; //5 mins

    return response()->json(['price' => $lines[0]->limit])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  public function oracle_pairs(Request $request)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_oracle'))
      abort(404);
    
    $ttl = 60; //1 min
    $httpttl = 60; //1 min
    $limit = 500; //500
    $hasMorePages = false;
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $direction = $request->input('direction');
    $direction = $direction == 'asc' ? 'asc':'desc';

    $validator = Validator::make([
      'page' => $page,
      'oracle' => $request->input('oracle'),
      'provider' => $request->input('provider'),
      'base' => $request->input('base'),
      'quote' => $request->input('quote'),
      'onlyfreshminutes' => $request->input('onlyfreshminutes')?(int)$request->input('onlyfreshminutes'):null,
    ], [
      'page' => 'required|int',
      'base' => 'nullable|string|alpha_num:ascii',
      'quote' => 'nullable|string|alpha_num:ascii',
      'onlyfreshminutes' => 'nullable|int|min:1',
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    

    $oracles = Oracle::select(
      DB::raw('CONCAT(`base`,\'.\',`quote`) as bq'), //add new compound column instead of CONCAT?
      DB::raw('ANY_VALUE(base) as base'),
      DB::raw('ANY_VALUE(quote) as quote'),

      # Select sub-rows via GROUP_CONCAT (most efficient way)
      //DB::raw('GROUP_CONCAT(DISTINCT provider ORDER BY id asc SEPARATOR \'|\' ) AS providers'),
      DB::raw('GROUP_CONCAT(`oracle` ORDER BY id asc SEPARATOR \'|\' ) AS oracles'),
      DB::raw('GROUP_CONCAT(`provider` ORDER BY id asc SEPARATOR \'|\' ) AS providers'),
      DB::raw('GROUP_CONCAT(`documentid` ORDER BY id asc SEPARATOR \'|\' ) AS documentids'),
      DB::raw('GROUP_CONCAT(`last_value` ORDER BY id asc SEPARATOR \'|\' ) AS last_values'),
      DB::raw('GROUP_CONCAT(`updated_at` ORDER BY id asc SEPARATOR \'|\' ) AS updated_ats')
    );


    if($request->input('base')) {
      $search_base = \trim($request->input('base'));
      if(\strlen($search_base) > 3 && \strlen($search_base) != 40) {
        //convert "USDT" (or usdt to USDT) to hex and search by that
        $search_base_normal = \str_pad(\bin2hex($search_base),40,'0',STR_PAD_RIGHT);
        $search_base_uppercase = \str_pad(\bin2hex(\Str::upper($search_base)),40,'0',STR_PAD_RIGHT);
        $oracles = $oracles->where(function($q) use ($search_base_normal, $search_base_uppercase) {
          $q->where('base', $search_base_normal)
            ->orWhere('base', $search_base_uppercase);
        });
      } else {
        $oracles = $oracles->where(DB::raw('UPPER(`base`)'),\Str::upper($search_base));
      }
      unset($search_base);
      unset($search_base_normal);
      unset($search_base_uppercase);
    }

    if($request->input('quote')) {
      $search_quote = \trim($request->input('quote'));
      if(\strlen($search_quote) > 3 && \strlen($search_quote) != 40) {
        //convert "USDT" (or usdt to USDT) to hex and search by that
        $search_quote_normal = \str_pad(\bin2hex($search_quote),40,'0',STR_PAD_RIGHT);
        $search_quote_uppercase = \str_pad(\bin2hex(\Str::upper($search_quote)),40,'0',STR_PAD_RIGHT);
        $oracles = $oracles->where(function($q) use ($search_quote_normal, $search_quote_uppercase) {
          $q->where('quote', $search_quote_normal)
            ->orWhere('quote', $search_quote_uppercase);
        });
      } else {
        $oracles = $oracles->where(DB::raw('UPPER(`quote`)'),\Str::upper($search_quote));
      }
      unset($search_quote);
      unset($search_quote_normal);
      unset($search_quote_uppercase);
    }

    if($request->input('provider')) {
      $oracles = $oracles->where(DB::raw('UPPER(`provider`)'),\Str::upper($request->input('provider')));
    }

    if($request->input('oracle')) {
      $oracles = $oracles->where('oracle',$request->input('oracle'));
    }

    if($request->input('onlyfreshminutes')) {
      $onlyfreshminutes = (int)$request->input('onlyfreshminutes');
      if($onlyfreshminutes) {
        $timecheck = now()->utc()->addMinutes(-$onlyfreshminutes);
        $oracles = $oracles->where('updated_at','>=',$timecheck);
      }
    }

    $num_results = $oracles->count(DB::raw('DISTINCT CONCAT(`base`, \'.\', `quote`)')); //count before grouping
    $oracles = $oracles->groupBy(DB::raw('CONCAT(`base`,\'.\',`quote`)')); //need to recalculate due to count(*) as aggregate
    $pages = (int)\ceil($num_results / $limit);
    if($pages < 1) $pages = 1;
    if($page > $pages)
      abort(404);

    if($num_results > $limit*$page)
      $hasMorePages = true;

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);
    
    $oracles = $oracles->orderBy('bq',$direction)->limit($limit)->offset($offset)->get();

    $r = [];
    foreach($oracles as $row) {
      $_row = [
        'base' => $row->base,
        'quote' => $row->quote,
        'base_formatted' => xrp_currency_to_symbol($row->base),
        'quote_formatted' => xrp_currency_to_symbol($row->quote),
        'oracles' => []
      ];
      $_row_oracles = \explode('|',$row->oracles);
      $_row_providers = \explode('|',$row->providers);
      $_row_documentids = \explode('|',$row->documentids);
      $_row_last_values = \explode('|',$row->last_values);
      $_row_updated_ats = \explode('|',$row->updated_ats);
      foreach($_row_oracles as $_key => $_value) {

        $_row_updated_at_utc = new \Carbon\Carbon($_row_updated_ats[$_key]);
        $_row['oracles'][] = [
          'oracle' => $_row_oracles[$_key],
          'provider' => $_row_providers[$_key],
          'documentid' => (int)$_row_documentids[$_key],
          'last_value' => $_row_last_values[$_key],
          'updated_at' => (int)$_row_updated_at_utc->format('U'),
          //'updated_at_datetime' => $_row_updated_ats[$_key],
        ];
      }
      $r[] = $_row;
      unset($_row_oracles);
      unset($_row_providers);
      unset($_row_documentids);
      unset($_row_last_values);
      unset($_row_update_ats);

    }

    return response()->json([
      'success' => true,
      'page' => $page,
      'pages' => $pages,
      'more' => $hasMorePages,
      'total' => $num_results,
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  public function oracles(Request $request)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_oracle'))
      abort(404);

    $ttl = 60; //1 min
    $httpttl = 60; //1 min
    $limit = 2000; //2000
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;
    $order = 'updated_at'; //reserved (not used yet)
    $direction = $request->input('direction');
    $direction = $direction == 'asc' ? 'asc':'desc';
    
    $validator = Validator::make([
      'page' => $page,
      'oracle' => $request->input('oracle'),
      'provider' => $request->input('provider'),
      'base' => $request->input('base'),
      'quote' => $request->input('quote'),
      'onlyfreshminutes' => $request->input('onlyfreshminutes')?(int)$request->input('onlyfreshminutes'):null,
    ], [
      'page' => 'required|int',
      'oracle' => ['nullable',new \App\Rules\XRPAddress, 'alpha_num:ascii'],
      'provider' => 'nullable|string|alpha_num:ascii',
      'base' => 'nullable|string|alpha_num:ascii',
      'quote' => 'nullable|string|alpha_num:ascii',
      'onlyfreshminutes' => 'nullable|int|min:1',
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $oracles = Oracle::select('oracle','provider','documentid','base','quote','last_value','updated_at');

    if($request->input('oracle')) {
      $oracles = $oracles->where('oracle',$request->input('oracle'));
    }

    if($request->input('provider')) {
      $oracles = $oracles->where(DB::raw('UPPER(`provider`)'),\Str::upper($request->input('provider')));
    }

    if($request->input('base')) {
      $search_base = \trim($request->input('base'));
      if(\strlen($search_base) > 3 && \strlen($search_base) != 40) {
        //convert "USDT" (or usdt to USDT) to hex and search by that
        $search_base_normal = \str_pad(\bin2hex($search_base),40,'0',STR_PAD_RIGHT);
        $search_base_uppercase = \str_pad(\bin2hex(\Str::upper($search_base)),40,'0',STR_PAD_RIGHT);
        $oracles = $oracles->where(function($q) use ($search_base_normal, $search_base_uppercase) {
          $q->where('base', $search_base_normal)
            ->orWhere('base', $search_base_uppercase);
        });
      } else {
        $oracles = $oracles->where(DB::raw('UPPER(`base`)'),\Str::upper($search_base));
      }
      unset($search_base);
      unset($search_base_normal);
      unset($search_base_uppercase);
    }

    if($request->input('quote')) {
      $search_quote = \trim($request->input('quote'));
      if(\strlen($search_quote) > 3 && \strlen($search_quote) != 40) {
        //convert "USDT" (or usdt to USDT) to hex and search by that
        $search_quote_normal = \str_pad(\bin2hex($search_quote),40,'0',STR_PAD_RIGHT);
        $search_quote_uppercase = \str_pad(\bin2hex(\Str::upper($search_quote)),40,'0',STR_PAD_RIGHT);
        $oracles = $oracles->where(function($q) use ($search_quote_normal, $search_quote_uppercase) {
          $q->where('quote', $search_quote_normal)
            ->orWhere('quote', $search_quote_uppercase);
        });
      } else {
        $oracles = $oracles->where(DB::raw('UPPER(`quote`)'),\Str::upper($search_quote));
      }
      unset($search_quote);
      unset($search_quote_normal);
      unset($search_quote_uppercase);
    }

    if($request->input('onlyfreshminutes')) {
      $onlyfreshminutes = (int)$request->input('onlyfreshminutes');
      if($onlyfreshminutes) {
        $timecheck = now()->utc()->addMinutes(-$onlyfreshminutes);
        $oracles = $oracles->where('updated_at','>=',$timecheck);
      }
    }

    $num_results = $oracles->count();

    $pages = (int)\ceil($num_results / $limit);
    if($pages < 1) $pages = 1;
    if($page > $pages)
      abort(404);

    if($num_results > $limit*$page)
      $hasMorePages = true;

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

    $oracles = $oracles->orderBy($order,$direction)->limit($limit)->offset($offset)->get();
    $r = [];
    foreach($oracles as $row) {
      $r[] = [
        'oracle' => $row->oracle,
        'provider' => $row->provider,
        'documentid' => (int)$row->documentid,
        'base' => $row->base,
        'quote' => $row->quote,
        'base_formatted' => xrp_currency_to_symbol($row->base),
        'quote_formatted' => xrp_currency_to_symbol($row->quote),
        'last_value' => $row->last_value,
        'timestamp' => (int)$row->updated_at->format('U'),
      ];
    }

    return response()->json([
      'success' => true,
      'page' => $page,
      'pages' => $pages,
      'more' => $hasMorePages,
      'total' => $num_results,
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}
