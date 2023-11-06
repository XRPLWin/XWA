<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StaticController extends Controller
{
  /**
   * Serve webp avatar if exists on disk
   * @return Response
   */
  public function avatar_serve(string $address)
  {
    $webpPath = resource_path('static/avatars/'.$address.'.webp');
    if(!\is_file($webpPath))
      abort(404);

    $ttl = 31556926; //1y
    $httpttl = 31556926; //1y
  
    return response()->file($webpPath, [
      'Cache-control' => 'public, s-max-age='.$ttl.', max_age='.$httpttl,
      'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl)
    ]);
  }
}
