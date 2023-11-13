<?php

namespace App\Repository\Sql;

#use App\Models\BTransaction;
use Illuminate\Support\Facades\DB;

class TransactionsRepository extends Repository
{
  /**
   * Fetches one record from database.
   * @param ?array $orderBy - ['t','desc'] or [ ['t','desc'], ['l','desc'],... ]
   * @return ?\stdClass
   */
  public static function fetchOne(string $yyyymm, array $where, array $select = null, ?array $orderBy = null): ?\stdClass
  {
    return self::fetchMany($yyyymm, $where, $select, $orderBy, 1)->first();
  }

  public static function fetchMany(string $yyyymm, array $where, array $select, ?array $orderBy, int $limit)
  {
    if(count($select) == 0)
      throw new \Exception('Please define columns to select (none found)');

    $results = DB::table(transactions_db_name($yyyymm))
      ->select($select);
    foreach($where as $k => $v) {
      $results = $results->where($k,$v);
    }

    if($orderBy !== null) {
      if(\is_array($orderBy[0])) {
        foreach($orderBy as $subOrderBy) {
          $results = $results->orderBy($subOrderBy[0],$subOrderBy[1]);
        }
      } else {
        $results = $results->orderBy($orderBy[0],$orderBy[1]);
      }   
    }
    return $results->limit($limit)->get();
  }
  
}