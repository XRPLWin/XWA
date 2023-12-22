<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\MetricHook;
use App\Utilities\HookLoader;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class HookController extends Controller
{
  /**
   * Hook data can be changed in case of destroyed hook or hook is reinstalled.
   * Keep HTTP cache small, and proxy cache purgable.
   */
  public function hook(string $hookhash)
  {
    $validator = Validator::make(['hook' => $hookhash], ['hook' => ['string',  new \App\Rules\Hook]]);
    if($validator->fails())
      abort(422, 'Hook format is invalid');

    $ttl = 604800; //7 days - this should be purged
    $httpttl = 600; //10 mins
    $hooks = HookLoader::getByHash($hookhash);
    //decorate results
    $r = [];
    foreach($hooks as $k => $hook) {
      $r[$k] = $hook;
      $r[$k]['is_active'] = $hook->is_active;
    }
    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }

  /**
   * Get metrics from table metric_hooks
   * @param string $hook - hook hash
   * @param string $from - Y-m-d
   * @param string $to   - Y-m-d
   */
  public function hook_metrics(string $hookhash, string $from, string $to, Request $request)
  {
    $ttl = 600; //10 mins
    $httpttl = 600; //10 mins

    $limit = 3; //1000
    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $validator = Validator::make([
      'hookhash' => $hookhash,
      'from' => $from,
      'to' => $to,
      'page' => $page,
    ], [
      'hookhash' => [new \App\Rules\Hook],
      'from' => 'required|date_format:Y-m-d',
      'to' => 'required|date_format:Y-m-d',
      'page' => 'required|int'
    ]);
  
    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $from = Carbon::createFromFormat('Y-m-d', $from);
    $to = Carbon::createFromFormat('Y-m-d', $to);

    if(!$from->isBefore($to))
      abort(422, 'To date can not be before From date');
    
    if($from->isFuture() || $to->isFuture())
      abort(422, 'From and To needs to be current date or past');

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

    # The Query:
    $metrics = MetricHook::where('hook',$hookhash)
      ->whereDate('day','>=',$from)->whereDate('day','<=',$to)
      ->limit($limit+1)
      ->offset($offset)
      ->orderBy('day','asc')
      ->get();

    if($page == 1) {
      $num_results = $metrics->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        $num_results = MetricHook::where('hook',$hookhash)
          ->whereDate('day','>=',$from)->whereDate('day','<=',$to)
          ->count();
      }
    } else {
      $num_results = MetricHook::where('hook',$hookhash)
          ->whereDate('day','>=',$from)->whereDate('day','<=',$to)
          ->count();
    }

    if($metrics->count() == $limit+1) $hasMorePages = true;

    $r = [];
    $i = 0;
    foreach($metrics as $m) {
      $i++;
      if($i == $limit+1) break; //remove last row (+1) from resultset
      $r[] = $m->toArray();
    }

    $pages = 1;
    if($hasMorePages) {
      $pages = (int)\ceil($num_results / $limit);
    }

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

  public function hook_transactions(string $hookhash)
  {
    $validator = Validator::make(['hook' => $hookhash], ['hook' => ['string',  new \App\Rules\Hook]]);
    if($validator->fails())
      abort(422, 'Hook format is invalid');

    abort(403,'Under construction');
    //todo validate $hook input

    $ttl = 300; //5 mins todo if hook version is completed long cache time
    $httpttl = 300; //5 mins todo if hook version is completed long cache time

    $searchparams = [];
    HookLoader::getTransactions($hook,$searchparams); //todo
  }
}
