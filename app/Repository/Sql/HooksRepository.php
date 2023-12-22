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
        'owner',
        'ctid_from',
        'ctid_to',
        'hookon',
        'params',
        'namespace',
        'stat_active_installs',
        'stat_installs',
        'stat_uninstalls',
        'stat_exec',
        'stat_exec_rollbacks',
        'stat_exec_accepts',
        'stat_exec_other'
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

  public static function fetch(?array $select, array $AND, array $orderBy, int $limit = 1, int $offset = 0)
  {
    if($select === null)
      $select = [
        'hook',
        'owner',
        'ctid_from',
        'ctid_to',
        'hookon',
        'params',
        'namespace',
        'stat_active_installs',
        'stat_installs',
        'stat_uninstalls',
        'stat_exec',
        'stat_exec_rollbacks',
        'stat_exec_accepts',
        'stat_exec_other'
      ];
    $r = DB::table('hooks')
      ->select($select)
      ->orderBy($orderBy[0],$orderBy[1])
      ->limit($limit)
      ->offset($offset);

    //AND conditions:
    foreach($AND as $v) {
      $c = count($v);
      if($c == 3){
        $r = $r->where($v[0],$v[1],$v[2]);
      } elseif($c == 2) {
        if(\is_array($v[1])) {
          $r = $r->where(function($q) use ($v) {
            $q->where($v[0],$v[1][0])->orWhere($v[0],$v[1][1]); //($v[1][0] OR $v[1][1])
          });
        } else {
          $r = $r->where($v[0],$v[1]);
        }
      } else {
        throw new \Exception('Invalid AND parameters');
      }
      unset($c);
    }
    $r = $r->get();
    if(!$r->count()) return null;
    return $r->toArray();
  }

  public static function count(array $AND)
  {
    $r = DB::table('hooks');
    
    //AND conditions:
    foreach($AND as $v) {
      $c = count($v);
      if($c == 3){
        $r = $r->where($v[0],$v[1],$v[2]);
      } elseif($c == 2){
        if(\is_array($v[1])) {
          $r = $r->where(function($q) use ($v) {
            $q->where($v[0],$v[1][0])->orWhere($v[0],$v[1][1]); //($v[1][0] OR $v[1][1])
          });
        } else {
          $r = $r->where($v[0],$v[1]);
        }
      } else {
        throw new \Exception('Invalid AND parameters');
      }
      unset($c);
    }
    return $r->count();
  }
}