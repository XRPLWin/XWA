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

  public static function fetchByLedgerIndexRange(int $start_li, int $end_li, ?string $select = null, string $where = '')
  {
    if($select === null)
      $select = 'first_l,last_l,vlkey,validators';

    $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlreports` WHERE ('.
             //'WHERE first_l BETWEEN '.$start_li.' AND '.$end_li.' ORDER BY first_l ASC';
             //'WHERE (first_l BETWEEN '.$start_li.' AND '.$end_li.') OR (last_l BETWEEN '.$start_li.' AND '.$end_li.') ORDER BY first_l ASC';
             //INNER:
             '(first_l between '.$start_li.' AND '.$end_li.' AND last_l between '.$start_li.' AND '.$end_li.')'.
             //BORDER RIGHT:
             ' OR (first_l between '.$start_li.' AND '.$end_li.' AND last_l >= '.$end_li.')'.
             //BORDER LEFT:
             ' OR (first_l <= '.$start_li.' AND last_l between '.$start_li.' AND '.$end_li.')'.
             //OUTER:
             ' OR (first_l <= '.$start_li.' AND last_l >= '.$end_li.')) '.$where.' ORDER BY first_l ASC';
    //dd($query);
    try {
      $results = \BigQuery::runQuery(\BigQuery::query(\trim($query)));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
    
    $r = [];
    foreach($results->rows(['returnRawResults' => false]) as $row) {
      $r[] = $row;
    }
    return $r;
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