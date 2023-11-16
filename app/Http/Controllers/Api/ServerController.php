<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
#use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServerController extends Controller
{

  /**
  * Returns info about queued jobs.
  */
  public function queue()
  {
    if(config('xwa.sync_type') != 'account')
      abort(404);

    $jobs = DB::table('jobs')->select([
      'id',
      'queue',
      'attempts',
      'available_at',
      'qtype',
      'qtype_data'
      ])
      ->orderBy('available_at','asc')
      ->limit(50)
      ->get();

    return response()->json($jobs);
  }

  public function syncstatus()
  {
    if(config('xwa.sync_type') != 'continuous')
      abort(404);
    
    $trackers = DB::table('synctrackers')->select([
      'id',
      'first_l',
      'last_synced_l',
      'last_l',
      'last_lt',
      'is_completed',
      'created_at',
      'updated_at'
      ])
      ->orderBy('first_l','asc')
      ->limit(100)
      ->get();

    return response()->json($trackers);
  }
}
