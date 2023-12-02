<?php

namespace App\Repository\Sql;

use App\Models\BHook;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HooksRepository extends Repository
{
  public static function fetchByHookHash(string $hookhash, bool $lockforupdate = false): ?array
  {
    $r = DB::table('hooks')
      ->select([
        'hook',
        'txid',
        'l_from',
        'l_to',
        'params',
        'title',
        'descr'
      ])
      ->where('hook',$hookhash);
      if($lockforupdate) {
        $r = $r->lockForUpdate()->get();
      } else {
        $r = $r->get();
      }
      if(!$r->count()) return null;

      return (array)$r->first();
  }
}