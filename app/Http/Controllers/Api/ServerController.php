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
}
