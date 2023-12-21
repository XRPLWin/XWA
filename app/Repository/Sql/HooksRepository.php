<?php

namespace App\Repository\Sql;

#use App\Models\BHook;
use Illuminate\Support\Facades\DB;
#use Carbon\Carbon;

class HooksRepository extends Repository
{
  public static function fetchByHookHashAndLedgerFrom(string $hookhash, string $ctid, bool $lockforupdate = false): ?array
  {
    $r = DB::table('hooks')
      ->select([
        'hook',
        //'txid',
        'owner',
        'ctid_from',
        'ctid_to',
        //'l_from',
        //'li_from',
        //'l_to',
        //'li_to',
        //'txid_last',
        'hookon',
        'params',
        'namespace',
        //'title',
        //'descr',
        'stat_active_installs',
        'stat_installs',
        'stat_uninstalls',
        'stat_exec',
        'stat_exec_rollbacks',
        'stat_exec_accepts',
        'stat_exec_other',
        //'stat_fee_min',
        //'stat_fee_max',
      ])
      ->where('hook',$hookhash)
      ->where('ctid_from',bchexdec($ctid));
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
        //'txid',
        'owner',
        'ctid_from',
        'ctid_to',
        //'l_from',
        //'li_from',
        //'l_to',
        //'li_to',
        //'txid_last',
        'hookon',
        'params',
        'namespace',
        //'title',
        //'descr',
        'stat_active_installs',
        'stat_installs',
        'stat_uninstalls',
        'stat_exec',
        'stat_exec_rollbacks',
        'stat_exec_accepts',
        'stat_exec_other',
        //'stat_fee_min',
        //'stat_fee_max',
      ])
      ->where('hook',$hookhash)
      ->orderBy('ctid_from','desc')
      ->get();

      return $r->toArray();
  }
}