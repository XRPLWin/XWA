<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Utilities\Ledger;
use Brick\Math\BigDecimal;

class XahauController extends Controller
{
  /**
   * Get list of transactions of type 36 (Import)
   */
  public function import(Request $request, string $from, string $to)
  {
    ini_set('memory_limit', '256M');

    $from = Carbon::createFromFormat('Y-m-d', $from)->startOfDay()->timezone('UTC');
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

    if($from->format('Ym') != $to->format('Ym')) {
      return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Dates must be within same month']],422);
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
    

    $results = DB::table(transactions_db_name($from->format('Ym')))
      ->select('address','h','t','fee','a as mint_xah','a2 as burn_xrp')
      ->where('l','>=',$li_start)
      ->where('l','<',$li_end)
      ->where('xwatype',36)
      ->where('isin',true)
      ->whereNotNull('a')
      ->whereNotNull('a2')
      ->orderBy('l','asc')
      ->limit(10000)
      ->get();

    foreach($results as $result) {
      $result->bonus_xah = 0;
      if($result->fee == 0) {
        //Bonus awarded for first tx
        $result->bonus_xah = (string)BigDecimal::of($result->mint_xah)->minus($result->burn_xrp);
      }
    }
    
    return response()->json(['success' => true,'data' => $results])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }
}