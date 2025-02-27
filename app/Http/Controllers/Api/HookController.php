<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MetricHook;
use App\Utilities\HookLoader;
use App\Models\BHook;
use App\Models\BHookTransaction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class HookController extends Controller
{
  /**
   * Hook data can be changed in case of destroyed hook or hook is reinstalled.
   * Keep HTTP cache small, and proxy cache purgable.
   */
  public function hook(string $hookhash)
  {
    $validator = Validator::make(['hook' => $hookhash], ['hook' => ['string',  new \App\Rules\Hook, 'alpha_num:ascii']]);
    if($validator->fails())
      abort(422, 'Hook format is invalid');

    $ttl = 600; //604800 - 7 days - this should be purged
    $httpttl = 600; //10 mins
    $hooks = HookLoader::getByHash($hookhash);
    //decorate results
    $r = [];
    foreach($hooks as $k => $hook) {
      $rArr = $hook->toArray();
      $rArr['ctid_from'] = bcdechex($rArr['ctid_from']);
      $rArr['is_active'] = $hook->is_active;
      if($rArr['ctid_to'] != '0')
        $rArr['ctid_to'] = bcdechex($rArr['ctid_to']);
      else
      $rArr['ctid_to'] = null;
      $r[] = $rArr;
      unset($rArr);
    }
    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  /**
   * List of all hooks
   * @param string $filter all|active|inactive
   * @param string $order created|activeinstalls|installs|uninstalls|executions|accepts|rollbacks|other
   * @param string $direction asc|desc
   * @param Request $request - [owner? => (string)rAccount, has_params? = (boolean)BOOL]
   */
  public function hooks(Request $request, string $filter, string $order, string $direction)
  {
    $ttl = 300; //5 mins todo if hook version is completed long cache time
    $httpttl = 300; //5 mins todo if hook version is completed long cache time

    $allowed_filters = ['all','active','inactive'];
    $allowed_orders = ['created','activeinstalls','installs','uninstalls','executions','accepts','rollbacks','other'];
    if(!in_array($filter,$allowed_filters))
      abort(422, 'Input filter parameter is invalid');

    if(!in_array($order,$allowed_orders))
      abort(422, 'Input order parameter is invalid');

    $limit = 200;
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $validator = Validator::make([
      'page' => $page,
      'owner' => $request->input('owner'),
      'hook' => $request->input('hook'),
      'has_params' => $request->input('has_params'),
    ], [
      'page' => 'required|int',
      'owner' => ['nullable',new \App\Rules\XRPAddress, 'alpha_num:ascii'],
      'hook' => ['nullable',new \App\Rules\Hook, 'alpha_num:ascii'],
      'has_params' => 'nullable|boolean'
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

    
    $AND = [];
    $ORDER = 'ctid_from'; //created (default)

    
    # Filter
    switch($filter) {
      case 'active':
        $AND[] = ['ctid_to','0'];
        break;
      case 'inactive':
        $AND[] = ['ctid_to','!=','0'];
        break;
    }

    # Owner
    if($request->input('owner')) {
      $AND[] = ['owner',$request->input('owner')];
    }
    # Hook
    if($request->input('hook')) {
      $AND[] = ['hook',$request->input('hook')];
    }
    
    # Has params
    $param_has_params = $request->input('has_params');
    if($param_has_params !== null) {
      if($param_has_params == true) {
        $AND[] = [DB::raw('json_length(params)'),'!=',0];
      } else {
        $AND[] = [DB::raw('json_length(params)'),'=',0];
      }
    }

    # Order
    switch($order) {
      case 'created':
        $ORDER = 'ctid_from';
        break;
      case 'activeinstalls':
        $ORDER = 'stat_active_installs';
        break;
      case 'installs':
        $ORDER = 'stat_installs';
        break;
      case 'uninstalls':
        $ORDER = 'stat_uninstalls';
        break;
      case 'executions':
        $ORDER = 'stat_exec';
        break;
      case 'accepts':
        $ORDER = 'stat_exec_accepts';
        break;
      case 'rollbacks':
        $ORDER = 'stat_exec_rollbacks';
        break;
      case 'other':
        $ORDER = 'stat_exec_other';
        break;
    }

    $direction = $direction == 'desc' ? 'desc':'asc';

    # The Query:
    $hooks = BHook::repo_fetch(null,$AND,[$ORDER,$direction],($limit+1),$offset);
    if($page == 1) {
      $num_results = $hooks->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        $num_results = BHook::repo_count($AND);
      }
    } else {
      $num_results = BHook::repo_count($AND);
    }

    if($hooks->count() == $limit+1) $hasMorePages = true;

    $r = [];
    $i = 0;
    foreach($hooks as $h) {
      $i++;
      if($i == $limit+1) break; //remove last row (+1) from resultset
      $rArr = $h->toArray();

      $rArr['ctid_from'] = bcdechex($rArr['ctid_from']);
      
      $rArr['is_active'] = $h->is_active;
      $rArr['li_from'] = decodeCTID($rArr['ctid_from'])['ledger_index'];
      $rArr['li_to'] = null;
      if($rArr['ctid_to'] != '0') {
        $rArr['ctid_to'] = bcdechex($rArr['ctid_to']);
        $rArr['li_to'] = decodeCTID($rArr['ctid_to'])['ledger_index'];
      }
      else
        $rArr['ctid_to'] = null;
      
      $r[] = $rArr;
      unset($rArr);
    }

    $pages = (int)\ceil($num_results / $limit);
    if($pages < 1) $pages = 1;
    if($page > $pages)
      abort(404);
    
    return response()->json([
      'success' => true,
      'page' => $page,
      'pages' => $pages,
      'more' => $hasMorePages,
      'total' => $num_results,
      //'info' => '',
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  /**
   * Get metrics from table metric_hooks
   * @param string $hook - hook hash
   * @param string $from - Y-m-d
   * @param string $to   - Y-m-d
   * @test http://xlanalyzer.test/v1/hook/ACD3E29170EB82FFF9F31A067566CD15F3A328F873F34A5D9644519C33D55EB7/C00468D10000535A/metrics/2023-02-01/2023-11-20
   */
  public function hook_metrics(Request $request, string $hookhash, string $hookctid, string $from, string $to) 
  {
    $ttl = 600; //10 mins
    $httpttl = 600; //10 mins

    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    //dd(bcdechex($hookctid));

    $validator = Validator::make([
      'hookhash' => $hookhash,
      'hookctid' => $hookctid,
      'from' => $from,
      'to' => $to,
      'page' => $page,
    ], [
      'hookhash' => [new \App\Rules\Hook, 'alpha_num:ascii'],
      'hookctid' => [new \App\Rules\CTID, 'alpha_num:ascii'],
      'from' => 'required|date_format:Y-m-d',
      'to' => 'required|date_format:Y-m-d',
      'page' => 'required|int'
    ]);
  
    if($validator->fails())
      return response()->json(['success' => false, 'error_code' => 1, 'errors' => ['Input parameters are invalid']],422);

    $from = Carbon::createFromFormat('Y-m-d', $from);
    $to = Carbon::createFromFormat('Y-m-d', $to);
    $hookctid64 = bchexdec($hookctid);

    if(!$from->isBefore($to))
      return response()->json(['success' => false, 'error_code' => 2, 'errors' => ['To date can not be before From date']],422);
    
    if($from->isFuture() || $to->isFuture())
      return response()->json(['success' => false, 'error_code' => 3, 'errors' => ['From and To needs to be current date or past date']],422);

    if($from->diffInDays($to) > 365)
      return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Date range too large (> 365 days)']],422);

    if(!$from->isToday() && !$to->isToday()) {
      $ttl = 604800; //7 days
      $httpttl = 604800; //7 days
    }
    //if(($from->isToday() || $to->isToday()) && Cache::get('job_xwaunlreports_sync_running'))
    //  abort(425,'Too early (resync job is running)');
    $select = ['day','num_active_installs','num_installs','num_uninstalls','num_exec','num_exec_accepts','num_exec_rollbacks','num_exec_other'];
    # The Query:
    $metrics = MetricHook::where('hook',$hookhash)
      ->select($select)
      ->whereDate('day','>=',$from)->whereDate('day','<=',$to)
      ->where('hook_ctid',$hookctid64)
      //->where('is_processed',true)
      ->orderBy('day','asc')
      ->limit(365) //1 year max
      ->get();
      //->groupBy('day');

   
    $days = CarbonPeriod::create($from,$to);

    $aggr = [];
    $i = 0;
    //$prevMetric = null;
    foreach($days as $day) {
      $ymd = $day->format('Y-m-d');
      //$aggr[$ymd] = [];
      $metric = $metrics->where('day',$ymd.' 00:00:00')->first();
      if($metric === null && $i == 0) {
        //no starting day metric, load first previous value, this is required so chart is fully filled
        $metric = MetricHook::where('hook',$hookhash)
          ->select($select)
          ->whereDate('day','<',$from)
          ->where('hook_ctid',$hookctid64)
          //->where('is_processed',true)
          ->orderBy('day','desc')
          ->first();
        if($metric === null) {
          //No initial metric found, fake it with zero values
          $metric = new MetricHook;
          $metric->day = $ymd;
          $metric->num_active_installs = 0;
        }
        
        $metric->num_installs = 0;
        $metric->num_uninstalls = 0;
        $metric->num_exec = 0;
        $metric->num_exec_accepts = 0;
        $metric->num_exec_rollbacks = 0;
        $metric->num_exec_other = 0;
      }
      
      if($metric === null) {
        //Use prev metric
        $aggr[$ymd] = $aggr[$day->addDays(-1)->format('Y-m-d')];
        $aggr[$ymd]['num_installs'] = 0;
        $aggr[$ymd]['num_uninstalls'] = 0;
        $aggr[$ymd]['num_exec'] = 0;
        $aggr[$ymd]['num_exec_accepts'] = 0;
        $aggr[$ymd]['num_exec_rollbacks'] = 0;
        $aggr[$ymd]['num_exec_other'] = 0;
      } else {
        $aggr[$ymd] = $metric->toArray();
      }
      $aggr[$ymd]['day'] = $ymd; //normalize
      $i++;
    }

    return response()->json([
      'success' => true,
      'hook' => $hookhash,
      'hook_ctid' => $hookctid,
      'data' => \array_values($aggr),
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }


  public function hook_active_accounts(Request $request, string $hookhash, string $hookctid, string $direction)
  {
    $ttl = 300; //5 mins todo if hook version is completed long cache time
    $httpttl = 300; //5 mins todo if hook version is completed long cache time
    $limit = 200; //200
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $validator = Validator::make([
      'hookhash' => $hookhash,
      'hookctid' => $hookctid,
      'page' => $page,
      'account' => $request->input('account'),
    ], [
      'hookhash' => [new \App\Rules\Hook, 'alpha_num:ascii'],
      'hookctid' => [new \App\Rules\CTID, 'alpha_num:ascii'],
      'page' => 'required|int',
      'account' => ['nullable',new \App\Rules\XRPAddress, 'alpha_num:ascii'],
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

    $hook = HookLoader::get($hookhash,$hookctid);
    if(!$hook)
      abort(404); //hook does not exist with that hash and exact ctid
    if($hook->ctid_to != '0') {
      //Destroyed hook, long cache
      $ttl = 604800; //7 days
      $httpttl = 604800; //7 days
    }

    $AND = [
      ['hook',$hookhash],
      ['ctid','>=',$hook->ctid_from],
      ['hookaction', '3']
    ];

    if($hook->ctid_to != '0') {
      $AND[] = ['ctid','<=',$hook->ctid_to];
    }

    # Owner
    if($request->input('account')) {
      $AND[] = ['r',$request->input('account')];
    }

    $direction = $direction == 'desc' ? 'desc':'asc';

    $txs = BHookTransaction::repo_fetch(['ctid','t','r'],$AND,['ctid',$direction],($limit+1),$offset);

    if($page == 1) {
      $num_results = $txs->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        $num_results = BHookTransaction::repo_count($AND);
      }
    } else {
      $num_results = BHookTransaction::repo_count($AND);
    }

    if($txs->count() == $limit+1) $hasMorePages = true;

    $r = [];
    $i = 0;
    
    foreach($txs as $tx) {
      $i++;
      if($i == $limit+1) break; //remove last row (+1) from resultset

      //due to aggregator might be slow to run this real time, make sure $r is unique by $tx->r (account) to prevent duplicates
      //if there is duplicate, only most recent date will be added:

      if(isset($r[$tx->r])) {
        //there is already added account to active accounts output, overwrite if $tx is more recent:
        if($tx->t->gt($r[$tx->r]['t'])) {
          //$tx is newer version of already set $r[$tx->r], overwrite it in output
          $r[$tx->r] = [
            'ctid' => bcdechex($tx->ctid),
            'r' => $tx->r,
            't' => $tx->t
          ];
        }
      } else {
        //add new row
        $r[$tx->r] = [
          'ctid' => bcdechex($tx->ctid),
          'r' => $tx->r,
          't' => $tx->t
        ];
      }
    }



    $pages = (int)\ceil($num_results / $limit);
    if($pages < 1) $pages = 1;
    if($page > $pages)
      abort(404);

    return response()->json([
      'success' => true,
      'page' => $page,
      'pages' => $pages,
      'more' => $hasMorePages,
      'total' => $num_results,
      //'info' => '',
      'hook' => $hook->hook,
      'hook_ctid' => bchexdec($hook->ctid_from),
      'data' => \array_values($r),
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }

  /**
   * Get list of hook transactions by hook specific version
   * Count is expensive (slow), disabled.
   * @param string $hookhash - Hook Hash
   * @param string $hookctid - Hook ctid when it was created (version selector)
   * @test http://xlanalyzer.test/v1/hook/012FD32EDF56C26C0C8919E432E15A5F242CC1B31AF814D464891C560465613B/C01B3B0C0000535A/transactions/created/desc
   */
  public function hook_transactions(Request $request, string $hookhash, string $hookctid, string $order, string $direction)
  {
    $ttl = 300; //5 mins default
    $httpttl = 300; //5 mins default

    $limit = 200; //200
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $order = 'created'; //reserved (not used yet)
    $direction = $direction == 'desc' ? 'desc':'asc';

    $validator = Validator::make([
      'hookhash' => $hookhash,
      'hookctid' => $hookctid,
      'page' => $page,
      'account' => $request->input('account'),
      'type' => $request->input('type'),
      'hookaction' => $request->input('hookaction'),
    ], [
      'hookhash' => [new \App\Rules\Hook, 'alpha_num:ascii'],
      'hookctid' => [new \App\Rules\CTID, 'alpha_num:ascii'],
      'page' => 'required|int',
      'account' => ['nullable',new \App\Rules\XRPAddress, 'alpha_num:ascii'],
      'type' => 'nullable|string|alpha_num:ascii',
      'hookaction' => 'nullable|int'
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');
    
    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);


    //dd(bchexdec($hookctid));
    $hook = HookLoader::get($hookhash,$hookctid);
    if(!$hook)
      abort(404); //hook does not exist with that hash and exact ctid
    if($hook->ctid_to != '0') {
      //Destroyed hook, long cache
      $ttl = 604800; //7 days
      $httpttl = 604800; //7 days
    }
    //dd($hook,$hook->ctid_to,$ttl);

    $AND = [
      ['hook',$hookhash],
      ['ctid','>=',$hook->ctid_from],
    ];

    if($hook->ctid_to != '0') {
      $AND[] = ['ctid','<=',$hook->ctid_to];
    }

    # Account
    if($request->input('account')) {
      $AND[] = ['r',$request->input('account')];
    }

    # Type
    if($request->input('type')) {
      $AND[] = ['txtype',$request->input('type')];
    }

    # Hookaction
    if($request->input('hookaction') !== null) {
      $_tmp = (int)$request->input('hookaction');
      if($_tmp == 3 || $_tmp == 34)
        $AND[] = ['hookaction',['3','34']];
      else
        $AND[] = ['hookaction',(string)$_tmp];
    }

    # The Query:
    $txs = BHookTransaction::repo_fetch(null,$AND,['id',$direction],($limit+1),$offset); //orderby ctid is slow, id (primary key) is fast
    //dd($txs);
    if($page == 1) {
      $num_results = $txs->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        //$num_results = BHookTransaction::repo_count($AND); //slow
      }
    } else {
      //$num_results = BHookTransaction::repo_count($AND); //slow
      $num_results = $limit * $page; //fake the counts due to backwards compatibility
      if($txs->count() == $limit+1){
        $num_results = $num_results+1;
      } else {
        $num_results = ($limit * ($page-1)) + $txs->count();
      }
    }

    if($txs->count() == $limit+1) $hasMorePages = true;

    $r = [];
    $i = 0;
    foreach($txs as $tx) {
      $i++;
      if($i == $limit+1) break; //remove last row (+1) from resultset
      $rArr = $tx->toArray();
      unset($rArr['id']);
      unset($rArr['hook']);
      $rArr['ctid'] = bcdechex($rArr['ctid']);
      $r[] = $rArr;
      unset($rArr);
    }

    $pages = (int)\ceil($num_results / $limit);
    if($pages < 1) $pages = 1;
    if($page > $pages)
      abort(404);
    
    return response()->json([
      'success' => true,
      'page' => $page,
      'pages' => $pages,
      'more' => $hasMorePages,
      'total' => $num_results,
      //'info' => '',
      'hook' => $hook->hook,
      'hook_ctid' => bchexdec($hook->ctid_from),
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
  
  public function hook_transactions_recent()
  {
    $ttl = 180;
    $httpttl = 180;

    $txs = BHookTransaction::repo_fetch(null,[],['id','desc'],20,0); //ctid sort is slow, id (primary key) is fast

    $r = [];
    $i = 0;
    foreach($txs as $tx) {
      $i++;
      $rArr = $tx->toArray();
      unset($rArr['id']);
      unset($rArr['hook']);
      $rArr['ctid'] = bcdechex($rArr['ctid']);
      $r[] = $rArr;
      unset($rArr);
    }

    return response()->json([
      'success' => true,
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl.', must-revalidate')
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  public function hook_name(string $hookhash)
  {
    $ttl = 3600;     //1hr
    $httpttl = 1800; //30 min

    $validator = Validator::make([
      'hookhash' => $hookhash
    ], [
      'hookhash' => [new \App\Rules\Hook, 'alpha_num:ascii'],
    ]);

    if($validator->fails())
      abort(422, 'Hook has has invalid format');

    $r = [
      't' => null,
      'i' => null,
      's' => null, //Source
      'principals' => null, //Author(s)
      'descr' => null, //Description - plain text (new lines are \n)
      'web' => null, //website url
    ];

    $info = config_static('hooks.'.$hookhash);
    //dd($info);
    if($info) {
      $r['t'] = $info['title'];
      $r['i'] = $info['image'];
      if(isset($info['source'])) $r['s'] = $info['source'];
      if(isset($info['principals'])) $r['principals'] = $info['principals'];
      if(isset($info['descr'])) $r['descr'] = $info['descr'];
      if(isset($info['web'])) $r['web'] = $info['web'];
    }

    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }
}
