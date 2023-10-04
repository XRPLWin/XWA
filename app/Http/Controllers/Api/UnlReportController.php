<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\Ledger;
use App\Models\BUnlreport;
use App\Models\BUnlvalidator;
use XRPLWin\UNLReportReader\UNLReportReader;
use Carbon\Carbon;
#use Illuminate\Http\Request;
#use Illuminate\Support\Facades\DB;

class UnlReportController extends Controller
{
  /**
   * Returns list of UNLReports (expanded)
   * Max range 31 days.
   * @return Response JSON
   */
  public function report(string $from, ?string $to = null)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    $ttl = 5259487; //5 259 487 = 2 months
    $httpttl = 172800; //172 800 = 2 days
      
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

    $minTime = ripple_epoch_to_carbon(config('xrpl.genesis_ledger_close_time'));
    if($minTime->gte($from)) {
      return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Requested date out of ledger range']],422);
    }
  
    $li_start = Ledger::getFromDate($from);
    if($to) { 
      $li_end = Ledger::getFromDate($to);
    } else {
      $li_end = Ledger::current();
      $ttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
      $httpttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
    }

    $reports = BUnlreport::fetchByRange($li_start,$li_end);
    return response()->json([
      'succes' => true,
      'ledger_index_start' => $li_start,
      'ledger_index_end' => $li_end,
      'count' => count($reports),
      'data' => $reports
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }

  //todo
  public function validators()
  {
    $ttl = 5259487; //5 259 487 = 2 months
    $httpttl = 172800; //172 800 = 2 days

    $data = BUnlreport::fetchValidators();

    return response()->json([
      'succes' => true,
      'data' => $data,
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }
}