<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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
   * Retrieves id from cache or database, caches.
   * @return ?int - null if not found
   */
  public static function getLedgerIndexForDay(Carbon $day): ?int
  {
    $cache_key = 'li_forday_'.$day->format('Ymd');
    $r = Cache::get($cache_key);
    if($r === null) {
      $ledgerindex = self::select('id')->where('day',$day)->first();
      if(!$ledgerindex)
        $r = 0;
      else
        $r = $ledgerindex->id;
      
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    
    if($r === 0) return null;

    return $r;
  }
}
