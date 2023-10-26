<?php

namespace App\Repository\Sql;

use App\Models\BUnlreport;
use Illuminate\Support\Facades\DB;

class UnlreportsRepository extends Repository
{
  /**
   * Load account data by ledger_index.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByLedgerIndex(int $ledger_index, ?string $select = null): ?array
  {
    return self::fetchOne('WHERE first_l <= '.$ledger_index.' AND last_l >= '.$ledger_index.' ORDER BY first_l ASC',$select);
  }

  /**
   * Fetches last row from unlreports.
   * @return ?\stdClass
   */
  public static function fetchLastRow(array $select = []): ?\stdClass
  {
    $result = DB::table('unlreports')
      ->select($select)
      ->orderBy('first_l','DESC')
      ->first();
    return $result;
  }

  public static function fetchByLedgerIndexRange(int $start_li, int $end_li): array
  {
    $query = DB::table('unlreports')->select(['first_l','last_l','vlkey','validators'])
      //INNER:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->whereBetween('first_l',[$start_li,$end_li]);
        $q->whereBetween('last_l',[$start_li,$end_li]);
      })
      //BORDER RIGHT:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->whereBetween('first_l',[$start_li,$end_li]);
        $q->where('last_l','>=',$end_li);
      })
      //BORDER LEFT:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->where('first_l','<=',$start_li);
        $q->whereBetween('last_l',[$start_li,$end_li]);
      })
      //OUTER:
      ->orWhere(function($q) use ($start_li,$end_li) {
        $q->where('first_l','<=',$start_li);
        $q->where('last_l','>=',$end_li);
      })
      ->orderBy('first_l','ASC');
    
    return $query->get()->toArray();
  }


  /**
   * Inserts one record to database.
   * @return bool true on success
   */
  public static function insert(array $values): bool
  {
    if(!count($values))
      throw new \Exception('Values missing');
    //$values['validators'] = \json_encode($values['validators']);
    return BUnlreport::insert($values);
  }
}