<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LedgerController extends Controller
{
  /**
   * Returns First Ledger index for specified date, date is UTC
   * @param string $ymd eg 2024-01-09
   * @return Response JSON
   */
  public function ledger_index_first(string $ymd)
  {
    $date = \Carbon\Carbon::createFromFormat('Y-m-d', $ymd)->startOfDay()->timezone('UTC');
    
    try {
      $li = \App\Utilities\Ledger::getFromDate($date);
    } catch (\Throwable $e) {
      return response()->json([
        'success' => false,
        'ledger_index' => null,
        'error' => $e->getMessage()
      ]);
    }

    $ttl = 15778463; //6 months
    $httpttl = 15778463; //6 months

    return response()->json([
        'success' => true,
        'ledger_index' => $li
      ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;

  }
}