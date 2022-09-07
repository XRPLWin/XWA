<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Utilities\Ledger;

class Ledgerindex extends Model
{
  public $table = 'ledgerindexes';
  public $timestamps = false;
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
      'day' => 'date',
  ];

  /**
   * Retrieves data from cache or database, caches.
   * @return ?array - null if not found
   */
  public static function getCachedLedgerindexDataForDay(Carbon $day): ?array
  {
    /*if($day->isToday())
    {
      $prevday = self::getCachedLedgerindexDataForDay(Carbon::yesterday());
      if(!$prevday)
        return $prevday;
      return [1,$prevday[1],Ledger::current()];
    }*/
    $cache_key = 'lid:'.$day->format('Ymd');
    $r = Cache::get($cache_key);
    if($r === null) {
      $ledgerindex = self::select('id','ledger_index_first','ledger_index_last')->where('day',$day)->first();
      if(!$ledgerindex)
        $r = 0;
      else
        $r = $ledgerindex->id.':'.$ledgerindex->ledger_index_first.':'.$ledgerindex->ledger_index_last;
      
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    if($r === 0 || $r === '0') return null;

    return \explode(':',$r);

    return $r;
  }

  /**
   * Retrieves (from cache or fetches)  ledger_index_first and ledger_index_last info about Ledgerindex
   * @return ?array [ledger_index_first,ledger_index_last]
   */
  public static function getLedgerindexData(int $id): ?array
  {
    $cache_key = 'li:'.$id;
    $r = Cache::get($cache_key);
    if($r === null) {
      $ledgerindex = self::select('ledger_index_first','ledger_index_last')->where('id',$id)->first();
      if(!$ledgerindex)
        $r = 0;
      else
        $r = (string)$ledgerindex->ledger_index_first.'.'.(string)$ledgerindex->ledger_index_last;
      
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    if($r === 0 || $r === '0') return null;

    return \explode('.',$r);
  }

  /**
   * Retrieves id from cache or database, caches.
   * @return ?int - null if not found
   */
  public static function getLedgerindexIdForDay(Carbon $day): ?int
  {
    $r = self::getCachedLedgerindexDataForDay($day);
    if(is_array($r))
      return $r[0];
    return null;
  }

  public static function getLedgerIndexFirstForDay(Carbon $day): ?int
  {
    $r = self::getCachedLedgerindexDataForDay($day);
    if(is_array($r))
      return $r[1];
    return null;
  }

  /**
   * @return int or string 'current' or null
   */
  public static function getLedgerIndexLastForDay(Carbon $day): ?int
  {
    $r = self::getCachedLedgerindexDataForDay($day);
    if(is_array($r))
      return $r[2];
      
    return null;
  }
}
