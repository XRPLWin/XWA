<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Utilities\Ledger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Storage;

class XahauController extends Controller
{

  /**
   * Get list of transactions of type 36 (Import)
   */
  public function import_aggr(Request $request, string $from, string $to)
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

    if($from->diffInDays($to) > 31) { //31
      return response()->json(['success' => false, 'error_code' => 3, 'errors' => ['Date range too large (> 31 days)']],422);
    }

    if($from->format('Ym') != $to->format('Ym')) {
      return response()->json(['success' => false, 'error_code' => 4, 'errors' => ['Dates must be within same month']],422);
    }

    //if($to->isToday()) {
    //  return response()->json(['success' => false, 'error_code' => 5, 'errors' => ['Requested date is not over yet.']],422);
    //}

   
    $ttl = 5259487; //5 259 487 = 2 months
    $httpttl = 172800; //172 800 = 2 days

    if($to->isToday()) {
      $ttl = 60; //1 min
      $httpttl = 60; //1 min
    }

    $minTime = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'));
    
    if($minTime->gte($from)) {
     
      if($from->format('Y-m-d') == $minTime->format('Y-m-d')) {
        $from = $minTime->startOfDay(); //same year and month, adjust to min starting date
      } else {
        return response()->json(['success' => false, 'error_code' => 5, 'errors' => ['Requested date out of ledger range']],422);
      }
    }

    
    $days = CarbonPeriod::create($from,$to);

    $r = [];
    foreach($days as $day) {
      if($day->isToday()) {
        $aggr = $this->import_aggr_day($day,true);
      } else {
        $ymd = $day->format('Ymd');
        $aggr = $this->getFromDisk($ymd);
        
        if($aggr === null) {
          $aggr = $this->import_aggr_day($day);
          //save data to disk
          $this->storeToDisk($ymd, $aggr);
        }
      }
      
      $aggr['t'] = $day->format('Y-m-d');
      $r[] = $aggr;
    }

    return response()->json(['success' => true,'data' => $r])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }

  private function import_aggr_day(Carbon $day, bool $isToday = false): array
  {
    $li_start = Ledger::getFromDate($day);
    if($isToday) {
      $li_end = null;
    } else {
      $day->addDays(1);
      $li_end = Ledger::getFromDate($day);
    }
    
    $results = DB::table(transactions_db_name($day->format('Ym')))
      ->select('h','fee','a as mint_xah','a2 as burn_xrp')
      ->where('l','>=',$li_start);
    if($li_end !== null)
      $results = $results->where('l','<',$li_end);
    $results = $results->where('xwatype',36)
      ->where('isin',true)
      ->whereNotNull('a')
      ->whereNotNull('a2')
      //->orderBy('l','asc')
      //->limit(10000)
      ->get();
    $data = [
      'num_txs' => $results->count(), //total number of transactions
      'num_txs_bonus' => 0, //number of transactions that yielded with bonus (first transaction reward)
      'xah_minted' => BigDecimal::zero(), //total xah minted (transfer + bonus)
      'xah_bonus' => BigDecimal::zero(), //total bonus awarded
      'xrp_burned' => BigDecimal::zero(), //total burned xrp
      'max_burn_amount' => BigDecimal::zero(), //maximum burned transaction amount
      'max_burn_tx' => null //transaction hash related to max_burn_amount
    ];

    foreach($results as $result) {
      $result->bonus_xah = 0;
      if($result->fee == 0) {
        //Bonus awarded for first tx
        $result->bonus_xah = (string)BigDecimal::of($result->mint_xah)->minus($result->burn_xrp);
        $data['xah_bonus'] = $data['xah_bonus']->plus($result->bonus_xah);
        $data['num_txs_bonus']++;
      }
      //SUM:
      $data['xah_minted'] = $data['xah_minted']->plus($result->mint_xah);
      $data['xrp_burned'] = $data['xrp_burned']->plus($result->burn_xrp);
      //Max burn
      $_tmp_burn_xrp = BigDecimal::of($result->burn_xrp);
      if($_tmp_burn_xrp->isGreaterThan($data['max_burn_amount'])) {
        $data['max_burn_amount'] = $_tmp_burn_xrp;
        $data['max_burn_tx'] = $result->h;
      }
    }
    return [
      'num_txs' => (string)$data['num_txs'],
      'xah_minted' => (string)$data['xah_minted'],
      'xrp_burned' => (string)$data['xrp_burned'],
      'xah_bonus' => (string)$data['xah_bonus'],
      'max_burn_amount' => (string)$data['max_burn_amount'],
      'max_burn_tx' => $data['max_burn_tx'],
    ];
  }

  /**
   * Store aggregated data to disk (cache it)
   */
  private function storeToDisk(string $ymd, array $data): bool
  {
    $internalpath = 'xahauimportaggr/'.$ymd.'.json';

    if(Storage::disk('local')->exists($internalpath))
        Storage::disk('local')->delete($internalpath); //update

    if(!Storage::disk('local')->put($internalpath, \json_encode($data))) {
      return false;
    }
    return true;
  }

  private function getFromDisk(string $ymd): ?array
  {
    $internalpath = 'xahauimportaggr/'.$ymd.'.json';
    if(Storage::disk('local')->exists($internalpath)) {
      $data = Storage::disk('local')->get($internalpath);
      return \json_decode($data,true);
    }
    return null;
  }


  /**
   * Get list of transactions of type 36 (Import)
   */
  /*public function import(Request $request, string $from, string $to)
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

  }*/
}