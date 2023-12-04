<?php

namespace App\Repository\Sql;

use App\Models\BHook;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HooksRepository extends Repository
{
  public static function fetchByHookHashAndLedgerFrom(string $hookhash, int $l_from, bool $lockforupdate = false): ?array
  {
    $r = DB::table('hooks')
      ->select([
        'hook',
        'txid',
        'l_from',
        'l_to',
        'hookon',
        'params',
        'title',
        'descr'
      ])
      ->where('hook',$hookhash)
      ->where('l_from',$l_from);
      if($lockforupdate) {
        $r = $r->lockForUpdate()->get();
      } else {
        $r = $r->get();
      }
      if(!$r->count()) return null;

      return (array)$r->first();
  }

  public static function fetchByHookHash(string $hookhash): array
  {
    $r = DB::table('hooks')
      ->select([
        'hook',
        'txid',
        'l_from',
        'l_to',
        'hookon',
        'params',
        'title',
        'descr'
      ])
      ->where('hook',$hookhash)
      ->orderBy('l_from','desc')
      ->get();

      return $r->toArray();
  }
}