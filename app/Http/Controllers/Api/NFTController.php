<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Nftfeed;

class NFTController extends Controller
{
  public function feed()
  {
    $feed = Nftfeed::select('ctid','type','t','nft','source','destination','broker','a','i','c','ba','bi','bc')
      ->orderBy('id','desc')
      ->limit(1000)
      ->get();

    $data = [];
    foreach($feed as $row) {
      $data[] = [
        'ctid' => \bcdechex($row->ctid),
        'type' => $row->type,
        't' => $row->t,
        'nft' => $row->nft,
        'source' => $row->source,
        'destination' => $row->destination,
        'broker' => $row->broker,
        'a' => $row->a,
        'i' => $row->i,
        'c' => $row->c,
        'ba' => $row->ba,
        'bi' => $row->bi,
        'bc' => $row->bc
      ];
    }

    return response()->json([
      'success' => true,
      'updated_at' => now()->timestamp,
      'data' => $data,
    ]);
  }
}
