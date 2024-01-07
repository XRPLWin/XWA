<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
#use Illuminate\Http\Request;
use GuzzleHttp\Client;
use XRPL_PHP\Core\Buffer;
use XRPL_PHP\Core\CoreUtilities;
use XRPL_PHP\Core\RippleAddressCodec\AddressCodec;

class ValidatorController extends Controller
{
  public function dunl()
  {
    $url = config('xrpl.'.config('xrpl.net').'.unl_vl');
    if(!$url)
      abort(404);

    $ttl = 600; //5 min, todo stavi vise, oko dan dva tri
    $httpttl = 600; //5 min

    $client = new Client();
    $response = $client->get($url);
    $code = $response->getStatusCode();
    if($code !== 200)
      abort(403);
      
    $json = (string)$response->getBody();
    $raw = \json_decode($json);
    if(!$raw)
      abort(403);
    $data = \json_decode(\base64_decode($raw->blob));

    $codec = new AddressCodec();

    $validators = [];
    foreach($data->validators as $v) {
      $validators[$v->validation_public_key] = [];
      if(\str_starts_with(\strtoupper($v->validation_public_key),'ED')) {
        $validators[$v->validation_public_key]['validator'] = $v->validation_public_key;  //ED
        $validators[$v->validation_public_key]['validator_n'] = $codec->encodeNodePublic(Buffer::from($v->validation_public_key)); //nX
        $validators[$v->validation_public_key]['account'] = CoreUtilities::deriveAddress(\strtoupper($v->validation_public_key)); //rX
      } else {
        $validators[$v->validation_public_key]['validator'] = $codec->decodeNodePublic($v->validation_public_key)->toString(); //ED
        $validators[$v->validation_public_key]['validator_n'] = $v->validation_public_key; //nX
        $validators[$v->validation_public_key]['account'] = CoreUtilities::deriveAddress(\strtoupper($validators[$v->validation_public_key]['validator'])); //rX
      }

      //Sequence:
      $man = \bin2hex(\base64_decode($v->manifest));
      $validators[$v->validation_public_key]['sequence'] = (int)\substr($man,2,8);
    }
    //unset($validators['EDB95B14B19007502F59151C598B49A1329E728223F950273D202044C6E5F3ABD6']);
    return response()->json([
      'url' => $url,
      'updated' => now(),
      'expiration' => $data->expiration,
      'sequence' => $data->sequence,
      'public_key' => $raw->public_key,
      'version' => $raw->version,
      'data' => \array_values($validators)
    ])->header('Cache-Control','public, s-max-age='.$ttl.', max_age='.$httpttl)
      ->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $httpttl));
  }
}
