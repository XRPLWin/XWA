<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
#use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Utilities\Ledger;

class XahauController extends Controller
{
  /**
   * Get list of transactions of type 36 (Import)
   */
  public function import(string $from, ?string $to = null)
  {
    ini_set('memory_limit', '256M');

    

    //dd($result);
    $from = Carbon::createFromFormat('Y-m-d', $from)->startOfDay()->timezone('UTC');
    if($to !== null)
      $to = Carbon::createFromFormat('Y-m-d', $to)->startOfDay()->timezone('UTC');

    if($from->gt($to)) {
      return response()->json(['success' => false, 'error_code' => 1, 'errors' => ['Requested from date larger than to date']],422);
    }

    if($from->isFuture() || $to->isFuture()) {
      return response()->json(['success' => false, 'error_code' => 2, 'errors' => ['Requested dates can not be in future']],422);
    }

    if($from->diffInDays($to) > 31) {
      return response()->json(['success' => false, 'error_code' => 3, 'errors' => ['Date range too large (> 31 days)']],422);
    }

    if(!$to->isToday()) {
      $to->addDay();
    } else {
      $to = null;
    }


    $ttl = 5259487; //5 259 487 = 2 months
    $httpttl = 172800; //172 800 = 2 days

    if($to->isToday()) {
      $ttl = 60; //1 min
      $httpttl = 60; //1 min
    }

    $li_start = Ledger::getFromDate($from);
    $li_end = Ledger::getFromDate($to);

    $results = DB::table(transactions_db_name('202401'))
      ->select('address','l','h','t','a')
      ->where('l','>',$li_start)
      ->where('l','<=',$li_end)
      ->where('xwatype',36)
      ->where('isin',true)
      ->whereNotNull('a')
      ->orderBy('l','asc')
      ->limit(100)
      ->get();

    $r = [];
    foreach($results as $result) {
      dd($result);
    }
    

    return response()->json(['success' => true,'data' => $result])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }
}