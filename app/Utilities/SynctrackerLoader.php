<?php

namespace App\Utilities;
use Illuminate\Support\Facades\Cache;
#use Illuminate\Support\Facades\DB;
use App\Models\Synctracker;

class SynctrackerLoader
{
  /**
   * 
   * @return array
   * @throws \Exception
   */
  public static function lastCompletedSyncedLedgerData()
  {
    $array = Cache::get('lastcompletedsyncedledgerdata');
    if($array == null) {
      $completedSynctracker = Synctracker::select('id','last_l','last_lt')
        ->where('is_completed',true)
        ->orderBy('first_l','ASC')
        ->first();
        $array  = $completedSynctracker->toArray();
      Cache::put('lastcompletedsyncedledgerdata', $array, 300);
    }
    return $array;
  }

}