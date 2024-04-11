<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
#use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Amm;

class AmmController extends Controller
{
  /**
   * Returns active AMM pools from amms table.
   */
  public function pools_active(Request $request)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_amm'))
      abort(404);

    $limit = 1000; //1000
    $ttl = 600; //20 mins
    $httpttl = 600; //20 mins

    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $validator = Validator::make([
      'page' => $page
    ], [
      'page' => 'required|int'
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

      

    $amms = Amm::select('accountid','c1','i1','a1','c2','i2','a2','lpc','lpi','lpa','h','t','tradingfee','synced_at')
      ->where('is_active',true)
      ->where('lpc','!=','') //make sure we return only synced
      ->orderBy('t','desc')
      ->limit($limit+1)->offset($offset)
      ->get();

    if($page == 1) {
      $num_results = $amms->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        $num_results = Amm::where('is_active',true)->where('lpc','!=','')->count();
      }
    } else {
      $num_results = Amm::where('is_active',true)->where('lpc','!=','')->count();
    }
    
    if($amms->count() == $limit+1) $hasMorePages = true;

    $r = [];
    $i = 0;
    foreach($amms as $h) {
      $i++;
      if($i == $limit+1) break; //remove last row (+1) from resultset
      $rArr = $h->toArray();
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
}