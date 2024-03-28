<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
#use Illuminate\Support\Facades\DB;
use App\Models\Token;
use Illuminate\Support\Facades\Cache;

class TokenController extends Controller
{
  public function all()
  {
    if(Cache::get('job_xwadownloadtokendata_running'))
      abort(425,'Too early');

    $tokens = Token::select(
        'issuers.issuer as i','issuers.title as it','issuers.is_verified as iv','issuers.is_kyc as ikyc','issuers.social_twitter as i_x',
        'tokens.currency as c','tokens.amount as a','tokens.num_trustlines as nt','tokens.num_holders as nh','tokens.num_offers as no','tokens.self_assessment_url as sau'
      )
      ->join('issuers', 'issuers.id', '=', 'tokens.issuer_id')
      ->orderBy('tokens.num_holders','desc')
      ->get()
    ;
    $num_issuers = $tokens->uniqueStrict('i')->count();
    $tokens = $tokens->toArray();

    //format currencies
    foreach($tokens as $k => $v) {
      $tokens[$k]['cf'] = xrp_currency_to_symbol($v['c'],$v['c']);
    }

    $ttl = 21600; //6 hours
    $httpttl = 21600; //6 hours

    return response()->json([
      'success' => true,
      'total_tokens' => count($tokens),
      'total_issuers' => $num_issuers,
      'data' => $tokens
    ])
      ->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl))
    ;
  }
}
