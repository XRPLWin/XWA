<?php

namespace App\Repository\Sql;

#use App\Models\BHook;
use Illuminate\Support\Facades\DB;
#use Carbon\Carbon;

class HookTransactionsRepository extends Repository
{

  public static function fetch(?array $select, array $AND, array $orderBy, int $limit = 1, int $offset = 0)
  {
    if($select === null)
      $select = ['id','hook','ctid','t','r','txtype','tcode','hookaction','hookresult','hookreturnstring'];

    $r = DB::table('hook_transactions')
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
    $r = DB::table('hook_transactions');
    
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