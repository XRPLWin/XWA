<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Utilities\Ledger;
use App\Models\BUnlreport;
use App\Models\BUnlvalidator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use XRPL_PHP\Core\RippleAddressCodec\AddressCodec;
use XRPL_PHP\Core\CoreUtilities;
use XRPL_PHP\Core\Buffer;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
#use XRPL_PHP\Core\CoreUtilities;
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
    $li_start = null;

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

    $minTime = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger_close_time'));
    if($minTime->gte($from)) {

      if($from->format('Y-m') == $minTime->format('Y-m')) {
        $from = $minTime->startOfDay(); //same year and month, adjust to min starting date
        $li_start = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
      } else {
        return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Requested date out of ledger range']],422);
      }
    }
  
    if($li_start === null) $li_start = Ledger::getFromDate($from);
    if($to) { 
      $li_end = Ledger::getFromDate($to);
    } else {
      $li_end = Ledger::validated();
      $ttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
      $httpttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
    }

    $reports = BUnlreport::repo_fetchByRange($li_start,$li_end);
    //dd($reports);
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
    $lastCheckedLedgerIndex = BUnlreport::repo_last(['last_l']);
    if(!$lastCheckedLedgerIndex) {
      $lastCheckedLedgerIndex = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
    } else {
      $lastCheckedLedgerIndex = $lastCheckedLedgerIndex->last_l;
    }
    if($lastCheckedLedgerIndex < 512) $lastCheckedLedgerIndex = 512;
    //

    $codec = new AddressCodec();
    $validators = BUnlvalidator::repo_fetchAll();
    $data = [];
    foreach($validators as $v) {

      $stats = $v->getStatistics($lastCheckedLedgerIndex);

      $data[$v->validator] = [
        'validator' => $v->validator,
        'validator_n' => $codec->encodeNodePublic(Buffer::from($v->validator)), //nX
        //'validator_acc' => CoreUtilities::deriveAddress(\strtoupper($v->validator)), //rX
        'account' => $v->account,
        'reliability' => number_format($stats['reliability'],0),
        'reliability_sort' => $stats['reliability'],
        'first_active_ledger_index' => $v->first_l,
        'last_active_ledger_index' => $v->last_l,
        'max_successive_ledger_indexes' => ($v->max_successive_fl_count*256),
        'current_successive_ledger_indexes' => ($v->current_successive_fl_count*256),
        'is_active' => $stats['is_active'],
        'domain' => $v->domain,
        'seq' => $v->seq,
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
   
    //unset($data['ED1E88D64F134456B4BCBBC5554FDE292CCF8585DED2CAADAF83A499B4276BE312']); //testing..

    return response()->json([
      'success' => true,
      'updated' => now(),
      'data' => \array_values($data),
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }

  private function xrplFetchValidatorManifest(string $validator): array
  {
    $success = false;
    //Domain and sequence:
    $domain = $seq = null;
    //query node to check if this validator is live, might fail for non-unl validators, in that case set 3 min ttl to check again next time
    $manifestReq = app(XRPLWinApiClient::class)->api('manifest')
    ->params([
        'public_key' => $validator, //public_key (nH..) or epidermal_key (n9..)
    ])
    ->send();
    $manifest = $manifestReq->finalResult();
    if(isset($manifest->details)) {
      //we have info
      $success = true;
      $domain = $manifest->details->domain;
      //Sequence
      $seq = $manifest->details->seq;
    }
    return [
      'success' => $success,
      'domain' => $domain,
      'seq' => $seq,
    ];
  }

  public function validator(string $validator) //nH
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    if(!\str_starts_with($validator,'nH')) {
      abort(404);
    }

    $ttl = 600; //10 mins
    $httpttl = 600; //10 mins
    $success = true;
    $r = null;

    $codec = new AddressCodec();
    
    $validator_ed = $codec->decodeNodePublic($validator)->toString(); //ED

    $v = BUnlvalidator::find($validator_ed); //This accepts only strtoupper ED ...
   
    if(!$v) {
      $success = true;
      $ttl = 3600; //1hr
      $httpttl = 3600; //1hr

      $manifest = $this->xrplFetchValidatorManifest($validator);
      if(!$manifest['success']) {
        //current serving node does not have the info for requested validator
        $ttl = 180; //3 min
        $httpttl = 180; //3 min
      }

      $r = [
        'validator' => $validator, //nH
        'isgov' => false, //never been in unlreports - no governance
        'account' => CoreUtilities::deriveAddress(\strtoupper($validator_ed)), //rX, //todo extract address
        'domain' => $manifest['domain'],
        'seq' => $manifest['seq'],
        'reliability' => null,
        'reliability_sort' => 0,
        'first_active_ledger_index' => null,
        'last_active_ledger_index' => null,
        'max_successive_ledger_indexes' => null,
        'current_successive_ledger_indexes' => null,
        'is_active' => null,
        
      ];
    } else {

      if(Cache::get('job_xwaunlreports_sync_running'))
        abort(425,'Too early (resync job is running)');

      if($v->domain === null) {
        //no domain stored:
        $manifest = $this->xrplFetchValidatorManifest($validator);
        if($manifest['success']) {
          $v->domain = $manifest['domain'];
          $v->seq = $manifest['seq'];
          $v->save();
        }
      }
      
      $lastCheckedLedgerIndex = BUnlreport::repo_last(['last_l']);
      if(!$lastCheckedLedgerIndex) {
        $lastCheckedLedgerIndex = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
      } else {
        $lastCheckedLedgerIndex = $lastCheckedLedgerIndex->last_l;
      }
      if($lastCheckedLedgerIndex < 512) $lastCheckedLedgerIndex = 512;
      //
      $stats = $v->getStatistics($lastCheckedLedgerIndex);

      $r = [
        'validator' => $codec->encodeNodePublic(Buffer::from($v->validator)), //nH
        'isgov' => true,
        'domain' => $v->domain,
        'seq' => $v->seq,
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

  public function validator_reports_daily(string $validator, string $from, string $to)
  {
    if(!config('xrpl.'.config('xrpl.net').'.feature_unlreport'))
      abort(404);

    //todo validate $validator input
    $ttl = 5259487; //5 259 487 = 2 months
    $httpttl = 172800; //172 800 = 2 days
    

    $li_start = null;
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

    if(($from->isToday() || $to->isToday()) && Cache::get('job_xwaunlreports_sync_running'))
      abort(425,'Too early (resync job is running)');

    $minTime = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger_close_time'));

    if($minTime->gte($from)) {

      if($from->format('Y-m') == $minTime->format('Y-m')) {
        $from = $minTime->startOfDay(); //same year and month, adjust to min starting date
        $li_start = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
      } else {
        return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Requested date out of ledger range']],422);
      }
    }

    if($to->isToday()) {
      $to = null;
    }
    if($li_start === null) $li_start = Ledger::getFromDate($from);
    
    if($to) {
      $_to = clone $to;
      $li_end = Ledger::getFromDate($_to->addDay());
    } else {
      $li_end = Ledger::validated();
      
      $to = now();
      $ttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
      $httpttl = 300; //5 mins, maybe 10 min? (ledger flag time is 12mins)
    }

    //$reports = BUnlreport::fetchByRangeForValidator($validator,$li_start,$li_end);
    $reports = BUnlreport::repo_fetchByRange($li_start,$li_end);
    //dd($validator);
    $v = BUnlvalidator::repo_find($validator,['first_l']);
    if(!$v) {
      return response()->json([
        'success' => true,
        'updated' => now(),
        'ledger_index_start' => $li_start,
        'ledger_index_end' => $li_end,
        'validator' => $validator,
        'data' => []
      ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
        ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
    }
    $reports = \array_reverse($reports);
    $days = CarbonPeriod::create($from,$to);

    $reports = collect($reports);
    $aggr = [];
    $minTime = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger_close_time'));
    $codec = new AddressCodec(); 

    foreach($days as $day) {

      if($minTime->gte($day)) {
        if($day->format('Y-m') == $minTime->format('Y-m')) {
          $l = config('xrpl.'.config('xrpl.net').'.feature_unlreport_first_flag_ledger');
        } else {
          continue; //skip (this day is before first ledger)
        }
      } else {
        $l = Ledger::getFromDate($day);
      }
      
      $_day = clone $day;
      $_day->addDay();
      if($_day->isFuture())
        $l2 = $li_end;
      else
        $l2 = Ledger::getFromDate($_day);
      unset($_day);
      $list = $reports->where('first_l','>=',$l)->where('first_l','<',$l2);

      if($list->count()) {
        $aggr[$day->format('Y-m-d')] = [
          'total' => $list->count(),
          'active_num' => 0,
          'inactive_num' => 0,
          'data' => []
        ];
        foreach($list as $item) {
          $_validators = [];
          foreach($item['validators'] as $_v){
            $_validators[$_v] = $codec->encodeNodePublic(Buffer::from($_v)); //nX
          }
          $item['validators'] = $_validators;
          if(isset($item['validators'][$validator])) {
            //requested validator is in this ledger range (one flag)
            $aggr[$day->format('Y-m-d')]['active_num']++;
            $item['active'] = true;
          } else {
            if($v->first_l > $item['first_l'])
              $item['active'] = null; //not yet activated
            else {
              $item['active'] = false; //requested validator is NOT in this ledger range (one flag)
              $aggr[$day->format('Y-m-d')]['inactive_num']++;
            }
          }
          $aggr[$day->format('Y-m-d')]['data'][] = $item;
        }
      }
      
      unset($list);
    }
    
    $aggr = \array_reverse($aggr);
    //dd($aggr);
    return response()->json([
      'success' => true,
      'updated' => now(),
      'ledger_index_start' => $li_start,
      'ledger_index_end' => $li_end,
      'validator' => $validator,
      'data' => $aggr
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));

  }
  
}