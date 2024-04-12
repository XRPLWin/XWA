<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
#use Illuminate\Support\Facades\DB;
#use Carbon\Carbon;
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

    $limit_default = 1000; //1000 (default)
    $ttl = 600; //20 mins
    $httpttl = 600; //20 mins

    $page = (int)$request->input('page');
    if(!$page) $page = 1;
    $hasMorePages = false;

    $limit = $request->input('limit') ? \abs((int)$request->input('limit')):$limit_default;
    if($limit < 1) $limit = $limit_default;
    if($limit > $limit_default) 
      $limit = $limit_default;

    $validator = Validator::make([
      'page' => $page,
      'search' => $request->input('search'),
    ], [
      'page' => 'required|int',
      'search' => 'nullable|string'
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');

    $offset = 0;
    if($page > 1)
      $offset = $limit * ($page - 1);

    $ammsQueryBilder = Amm::where('is_active',true)
      ->where('lpc','!=',''); //make sure we return only synced

    $search = \trim((string)$request->input('search'));
    
    if($search != '') {
      $search_ex = \explode('/',$search);
      if(count($search_ex) == 2) {
        //pair search:
        $ammsQueryBilder->where(function($q) use ($search_ex) {
          $q->where(function($q2) use ($search_ex) {
            $q2->where('c1_display',\trim($search_ex[0]))->where('c2_display',\trim($search_ex[1]));
          })
          ->orWhere(function($q2) use ($search_ex) {
            $q2->where('c1_display',\trim($search_ex[1]))->where('c2_display',\trim($search_ex[0]));
          });
        });
      } else {
        //single search:
        $ammsQueryBilder->where(function($q) use ($search) {
          $q = $q->where('c1_display',$search)
            ->orWhere('c2_display',$search);
          //if $search length is > 25 search also addresses
          if(\strlen($search) > 25) {
            $q = $q->orWhere('lpc',$search)
              ->orWhere('i1',$search)
              ->orWhere('i2',$search)
              ->orWhere('accountid',$search);
          }
        });
      }
    }

    $amms = $ammsQueryBilder
      ->select('accountid','c1','c1_display','i1','a1','c2','c2_display','i2','a2','lpc','lpi','lpa','h','t','tradingfee','synced_at')
      ->orderBy('t','desc')
      ->limit($limit+1)->offset($offset)
      ->get();


    /*$amms = Amm::select('accountid','c1','c1_display','i1','a1','c2','c2_display','i2','a2','lpc','lpi','lpa','h','t','tradingfee','synced_at')
      ->where('is_active',true)
      ->where('lpc','!=','') //make sure we return only synced
      ->orderBy('t','desc')
      ->limit($limit+1)->offset($offset)
      ->get();*/

    if($page == 1) {
      $num_results = $amms->count();
      if($num_results == $limit+1) {
        //has more pages, do count
        $num_results = $ammsQueryBilder->count();
      }
    } else {
      $num_results = $ammsQueryBilder->count();
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
      'limit' => $limit,
      'data' => $r,
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}