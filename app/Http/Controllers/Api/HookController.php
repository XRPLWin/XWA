<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Utilities\HookLoader;

class HookController extends Controller
{
  /**
   * Hook data can be changed in case of destroyed hook or hook is reinstalled.
   * Keep HTTP cache small, and proxy cache purgable.
   */
  public function hook(string $hookhash)
  {
    $ttl = 604800; //7 days - this should be purged
    $httpttl = 600; //10 mins
    $hooks = HookLoader::getByHash($hookhash);
    //decorate results
    $r = [];
    foreach($hooks as $k => $hook) {
      $r[$k] = $hook;
      $r[$k]['is_active'] = $hook->is_active;
    }
    return response()->json($r)
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}
