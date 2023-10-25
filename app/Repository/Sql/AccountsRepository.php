<?php

namespace App\Repository\Sql;

use App\Models\BAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountsRepository extends Repository
{
  /**
   * Load account data by address.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByAddress(string $address): ?array
  {
    $r = DB::table('accounts')
      ->select([
        'address',
        'l',
        'li',
        'lt',
        'activatedBy',
        'isdeleted'
      ])
      ->where('address',$address)
      ->get();
      if(!$r->count()) return null;

      return (array)$r->first();
  }

  public static function getFirstTransactionAllInfo(): array
  {
    $results = DB::table('transactions')->select('xwatype',DB::raw('MIN(`t`) as t'))->orderBy('t','asc')
      ->groupBy('xwatype')
      ->get();

    $collection = [];
    foreach($results as $row) {
      $collection[$row->xwatype] = Carbon::parse($row->t)->format('U');
    }
    return $collection;
  }

  # OLD BELOW

  /**
   * Fetches one record from database.
   * @return ?array
   */
  public static function fetchOne($where): ?array
  {
    $query = 'SELECT address,l,li,lt,activatedBy,isdeleted FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.accounts` WHERE '.$where.' LIMIT 1';
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

  /**
   * Inserts one record to database.
   * @return bool true on success
   */
  public static function insert(array $values): bool
  {
    if(!count($values))
      throw new \Exception('Values missing');

    $insert ='INSERT INTO `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.accounts` ('.\implode(',',\array_keys($values)).') VALUES (';
    $castedValues = self::valuesToCastedValues(BAccount::BQCASTS, $values);
    $insert .= \implode(',',$castedValues);
    $insert .= ')';
    //dd($insert,$values);
    try {
      \BigQuery::runQuery(\BigQuery::query($insert));
    } catch (\Throwable $e) {
      //dd($e->getMessage());
      throw $e;
      return false;
    }
    return true;
  }
}