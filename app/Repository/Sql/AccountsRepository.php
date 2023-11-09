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
  public static function fetchByAddress(string $address, bool $lockforupdate = false): ?array
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
      ->where('address',$address);
      if($lockforupdate) {
        $r = $r->lockForUpdate()->get();
      } else {
        $r = $r->get();
      }
      if(!$r->count()) return null;

      return (array)$r->first();
  }

  public static function getFirstTransactionAllInfo(string $address): array
  {
    $results = DB::table('transactions')->select('xwatype',DB::raw('MIN(`t`) as t'))
      ->where('address',$address)
      ->orderBy('t','asc')
      ->groupBy('xwatype')
      ->get();

    $collection = [];
    foreach($results as $row) {
      $collection[$row->xwatype] = Carbon::parse($row->t)->format('U');
    }
    return $collection;
  }

}