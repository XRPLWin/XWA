<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

class OracleController extends Controller
{

  /**
   * @see https://github.com/XRPL-Labs/XRPL-Persist-Price-Oracle/blob/main/README.md
   */
  public function usd()
  {
    $account_lines = app(XRPLWinApiClient::class)->api('account_lines')
      ->params([
          'account' => 'rXUMMaPpZqPutoRszR29jtC8amWq3APkx',
          'limit' => 1
      ]);

    try {
      $account_lines->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
      // Handle errors
      throw $e;
    }

    $lines = (array)$account_lines->finalResult();

    $ttl = 300; //5 mins
    $httpttl = 300; //5 mins

    return response()->json(['price' => $lines[0]->limit])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}
