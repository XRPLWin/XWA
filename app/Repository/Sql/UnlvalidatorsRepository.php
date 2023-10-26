<?php

namespace App\Repository\Sql;

use App\Models\BUnlvalidator;
use Illuminate\Support\Facades\DB;

class UnlvalidatorsRepository extends Repository
{
  
  public static function fetchAll(array $select = []): array
  {
    if(count($select) == 0)
      $select = [
        'validator',
        'account',
        'first_l',
        'last_l',
        'current_successive_fl_count',
        'max_successive_fl_count',
        'active_fl_count'
      ];

    return DB::table('unlvalidators')->select($select)->get()->toArray();
  }

  /**
   * Load account data by validator key.
   * @return ?array
   */
  public static function fetchByValidator(string $validator, array $select = []): ?array
  {
    if(count($select) == 0)
      $select = [
        'validator',
        'account',
        'first_l',
        'last_l',
        'current_successive_fl_count',
        'max_successive_fl_count',
        'active_fl_count'
      ];
    $r = DB::table('unlvalidators')->select($select)->where('validator',$validator)->first();
    if($r !== null)
      return (array)$r;
    return null;
  }

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne(string $where, ?string $select = null): ?array
  {
    if($select === null)
      $select = 'validator,account,first_l,last_l,current_successive_fl_count,max_successive_fl_count,active_fl_count';

    $query = 'SELECT '.$select.' FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.unlvalidators` '.$where.' LIMIT 1';
    
    try {
      $results = \BigQuery::runQuery(\BigQuery::query($query));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
    }
    $r = null;
    foreach ($results as $row) {
      $r = $row;
      break;
    }
    return $r;
  }

}