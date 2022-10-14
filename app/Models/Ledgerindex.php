<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
#use App\Utilities\Ledger;

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
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $fillable = ['ledger_index_first', 'ledger_index_last', 'day'];

  /**
   * The "booted" method of the model.
   *
   * @return void
   */
  protected static function booted()
  {
      static::saved(function ($model) {
          $model->flushCache();
      });
  }

  public function flushCache()
  {
    Cache::forget('lid:'.$this->day->format('Ymd'));
    if($this->id) {
      Cache::forget('li:'.$this->id);
    }
  }

  /**
   * Retrieves data from cache or database, caches.
   * @return ?array - null if not found
   */
  public static function getCachedLedgerindexDataForDay(Carbon $day): ?array
  {
    $cache_key = 'lid:'.$day->format('Ymd');
    $r = Cache::get($cache_key);
    
    if($r === null) {
      $ledgerindex = self::select('id','ledger_index_first','ledger_index_last')->where('day',$day)->first();
      //dd($ledgerindex);
      if(!$ledgerindex)
        $r = 0;
      else
        $r = $ledgerindex->id.':'.$ledgerindex->ledger_index_first.':'.$ledgerindex->ledger_index_last;
      
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    if($r === 0 || $r === '0') return null;

    $r = \explode(':',$r);

    $r[0] = (int)$r[0];                       //eg. 3257
    $r[1] = (int)$r[1];                       //eg. 680464090000
    $r[2] = ($r[2] === -1) ? -1 : (int)$r[2]; //eg. -1 or 680676479999

    return $r;
  }

  /**
   * Retrieves (from cache or fetches)  ledger_index_first and ledger_index_last info about Ledgerindex
   * @return ?array [(int)ledger_index_first,(string)ledger_index_last]
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

    $r = \explode('.',$r);

    $r[0] = (int)$r[0];
    $r[1] = ($r[1] === -1) ? -1 : (int)$r[1];
    return $r;
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
    {
      return $r[2];
    }
      
      
    return null;
  }
}
