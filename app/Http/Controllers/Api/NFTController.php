<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Nftfeed;
use Illuminate\Support\Facades\Validator;

class NFTController extends Controller
{
  public function feed(Request $request)
  {

    # Param: limit
    $limit = 100;
    if($request->input('limit')) {
      $limit = (int)$request->input('limit');
    }
    if($limit < 1) $limit = 1;
    if($limit > 1000) $limit = 1000;

    
    $validator = Validator::make([
      'addresses' => $request->input('addresses'),
      'type' => $request->input('type'),
    ], [
      'addresses' => 'nullable|array',
      'addresses.*' => ['nullable',new \App\Rules\XRPAddress, 'alpha_num:ascii'],
      'type' => 'nullable|int',
    ]);

    if($validator->fails())
      abort(422, 'Input parameters are invalid');
    
    # Param: address
    $addresses = $request->input('addresses');
    if(\is_array($addresses)) {
      $addresses = \array_unique($addresses);
      if(count($addresses) == 0)
        $addresses = null;
      elseif(count($addresses) > 20) {
        abort(422, 'Input parameters are invalid - too many addresses, max: 20 allowed');
      }
    }

    # Param: type
    $type = (int)$request->input('type');
    

    $feed = Nftfeed::select('ctid','type','t','nft','source','destination','broker','a','i','c','ba','bi','bc')
      ->orderBy('id','desc')
      ->limit($limit);
    if($type) {
      $feed = $feed->where('type',$type);
    }

    if(\is_array($addresses)) {
      $feed = $feed->where(function($q) use ($addresses) {
        foreach($addresses as $a) {
          $q->orWhere('source',$a)->orWhere('destination',$a)->orWhere('broker',$a);
        }
      });
    }
    $feed = $feed->get();

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
    $ttl = 60;
    $httpttl = 60;
    return response()->json([
      'success' => true,
      'updated_at' => now()->timestamp,
      'data' => $data,
    ])
    ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
    ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }
}
