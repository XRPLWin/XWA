<?php

namespace App\Utilities;
use XRPLWin\XRPL\Client;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPLLedgerTime\XRPLLedgerTimeSyncer;
use App\Models\Ledgerindextime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Ledger
{
  /**
   * Gets last current ledger, cached for 10 seconds.
   * @return int
   */
  public static function current(): int
  {
    $ledger_index = Cache::get('ledger_current');
    if($ledger_index === null) {
      $ledger_index = app(Client::class)->api('ledger_current')->send()->finalResult();
      Cache::put('ledger_current', $ledger_index, 10); //10 seconds
    }
    return $ledger_index;
  }

  /**
   * Gets last closed ledger, cached for 10 seconds.
   * @return int
   */
  public static function closed(): int
  {
    $ledger_index = Cache::get('ledger_closed');
    if($ledger_index === null) {
      $ledger_index = app(Client::class)->api('ledger_closed')->send()->finalResult();
      Cache::put('ledger_closed', $ledger_index, 10); //10 seconds
    }
    return $ledger_index;
  }

  /**
   * Gets last validated ledger, cached for 10 seconds.
   * Always one behind closed ledger.
   * @return int
   */
  public static function validated(): int
  {
    //return 1205;
    return self::closed()-1;
  }

  /**
   * Gets ledger_index from date
   * @param Carbon $date
   * @return int ledger_index
   */
  public static function getFromDate(Carbon $date)
  {
    $date = $date->startOfDay();
    if($date->isFuture()) {
      throw new \Exception('ledger getFromDate() - requested datetime is in future');
    }
    $l = Ledgerindextime::select('day_start','ledger_index')->whereDate('day_start',$date)->first();
    if($l === null) {
      $ledgerTime = new XRPLLedgerTimeSyncer([
        'ledgerindex_low' => config('xrpl.genesis_ledger')
      ],[
        'endpoint_fullhistory_uri' => config('xrpl.'.config('xrpl.net').'.rippled_fullhistory_server_uri')
      ]);
      $ledgerIndex = $ledgerTime->datetimeToLedgerIndex($date);
      DB::beginTransaction();
      $l = Ledgerindextime::select('id','day_start','ledger_index')->whereDate('day_start',$date)->first();
      if(!$l) {
        $l = new Ledgerindextime;
        $l->day_start = $date;
        $l->ledger_index = $ledgerIndex;
        $l->save();
      }
      DB::commit();
    }
    return $l->ledger_index;
  }
}