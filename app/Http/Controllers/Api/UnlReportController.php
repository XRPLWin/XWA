<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\Ledger;
use App\Models\BUnlreport;
use App\Models\BUnlvalidator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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

    if(Cache::get('job_xwaunlreports_sync_running'))
      abort(425,'Too early (resync job is running)');

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
      'success' => true,
      'updated' => now(),
      'ledger_index_start' => $li_start,
      'ledger_index_end' => $li_end,
      'count' => count($reports),
      'data' => $reports
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }

  /**
   * Returns list of validator with statistics
   * @return Response JSON
   */
  public function validators()
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    if(Cache::get('job_xwaunlreports_sync_running'))
      abort(425,'Too early (resync job is running)');

    $ttl = 600; //10 mins
    $httpttl = 600; //10 mins

    //
    $lastCheckedLedgerIndex = BUnlreport::last('last_l');
    if(!$lastCheckedLedgerIndex) {
      $lastCheckedLedgerIndex = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
    } else {
      $lastCheckedLedgerIndex = $lastCheckedLedgerIndex->last_l;
    }
    if($lastCheckedLedgerIndex < 512) $lastCheckedLedgerIndex = 512;
    //


    $validators = BUnlvalidator::fetchAll();
    $data = [];
    foreach($validators as $v) {

      $stats = $v->getStatistics($lastCheckedLedgerIndex);

      $data[$v->validator] = [
        'validator' => $v->validator,
        'account' => $v->account,
        'reliability' => number_format($stats['reliability'],0),
        'reliability_sort' => $stats['reliability'],
        'first_active_ledger_index' => $v->first_l,
        'last_active_ledger_index' => $v->last_l,
        'max_successive_ledger_indexes' => ($v->max_successive_fl_count*256),
        'current_successive_ledger_indexes' => ($v->current_successive_fl_count*256),
        'is_active' => $stats['is_active']
      ];
      unset($stats);
    }
    $list = collect($data)->sortByDesc('reliability_sort');

    //calculate rank:
    $prev = null;
    $data = [];
    $rank = 1;
    foreach($list->toArray() as $dk => $curr) {
      if($prev === null) {
        $prev = $curr;
      } else {
        if($prev['reliability'] != $curr['reliability']) {
          $rank++;
        }
      }
      $data[$dk] = $curr;
      $data[$dk]['rank'] = $rank;
      $prev = $curr;
    }

    return response()->json([
      'success' => true,
      'updated' => now(),
      'data' => \array_values($data),
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }

  public function validator(string $validator)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    $ttl = 600; //10 mins
    $httpttl = 600; //10 mins
    $success = true;
    $r = null;

    $v = BUnlvalidator::find($validator);
    if(!$v) {
      $success = false;
    } else {
      if(Cache::get('job_xwaunlreports_sync_running'))
        abort(425,'Too early (resync job is running)');

      //
      $lastCheckedLedgerIndex = BUnlreport::last('last_l');
      if(!$lastCheckedLedgerIndex) {
        $lastCheckedLedgerIndex = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
      } else {
        $lastCheckedLedgerIndex = $lastCheckedLedgerIndex->last_l;
      }
      if($lastCheckedLedgerIndex < 512) $lastCheckedLedgerIndex = 512;
      //
      $stats = $v->getStatistics($lastCheckedLedgerIndex);
      $r = [
        'validator' => $v->validator,
        'account' => $v->account,
        'reliability' => number_format($stats['reliability'],0),
        'reliability_sort' => $stats['reliability'],
        'first_active_ledger_index' => $v->first_l,
        'last_active_ledger_index' => $v->last_l,
        'max_successive_ledger_indexes' => ($v->max_successive_fl_count*256),
        'current_successive_ledger_indexes' => ($v->current_successive_fl_count*256),
        'is_active' => $stats['is_active']
      ];
    }
    return response()->json([
      'success' => $success,
      'updated' => now(),
      'data' => $r,
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }

  public function validator_reports_day(string $validator, string $day)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    //if $to or $from is today then: TODO
    if(Cache::get('job_xwaunlreports_sync_running'))
      abort(425,'Too early (resync job is running)');
  }

  public function validator_reports_daily(string $validator, string $from, string $to)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

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

    if($from->isToday() || $to->isToday) {
      if(Cache::get('job_xwaunlreports_sync_running'))
        abort(425,'Too early (resync job is running)');
    }
  }
  
}