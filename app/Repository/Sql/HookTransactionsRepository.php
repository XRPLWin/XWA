<?php

namespace App\Repository\Sql;

#use App\Models\BHook;
use Illuminate\Support\Facades\DB;
#use Carbon\Carbon;

class HookTransactionsRepository extends Repository
{

  public static function fetch(string $hookhash,?array $select,array $AND, array $orderBy, int $limit = 1)
  {
    if($select === null)
      $select = ['id','hook','h','l','t','r','txtype','tcode','hookaction','hookresult'];

    $r = DB::table('hook_transactions')
      ->select($select)
      ->where('hook',$hookhash)
      ->orderBy($orderBy[0],$orderBy[1])
      ->limit($limit);
    
    //AND conditions:
    foreach($AND as $k => $v) {
      $r = $r->where($k,$v);
    }
    $r = $r->get();

    if(!$r->count()) return null;
    return $r->toArray();
  }
}