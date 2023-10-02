<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\Ledger;
use XRPLWin\UNLReportReader\UNLReportReader;
use Carbon\Carbon;
#use Illuminate\Http\Request;
#use Illuminate\Support\Facades\DB;

class UnlReportController extends Controller
{
  public function index(string $from, ?string $to = null)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);
      
    $from = Carbon::createFromFormat('Y-m-d', $from)->startOfDay()->timezone('UTC');
    if($to !== null)
      $to = Carbon::createFromFormat('Y-m-d', $to)->addDay()->timezone('UTC');
    
    $li_start = Ledger::getFromDate($from);
    if($to) {
      $li_end = Ledger::getFromDate($to);
    } else {
      $li_end = Ledger::current();
    }
    //
    $reader = new UNLReportReader(config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri'));
   // dd($li_start, $li_end);
    $reports = $reader->fetchRange($li_start, $li_end); //array
    //dd($li_start,$li_end,$reports);


    return response()->json($reports);

  }
}