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
      if(\str_starts_with($v->validation_public_key,'ED')) {
        $validators[$v->validation_public_key]['public_key_ed'] = $v->validation_public_key;  //ED
        $validators[$v->validation_public_key]['public_key'] = $codec->encodeNodePublic(Buffer::from($v->validation_public_key)); //nX
        $validators[$v->validation_public_key]['address'] = CoreUtilities::deriveAddress(\strtoupper($v->validation_public_key)); //rX
      } else {
        $validators[$v->validation_public_key]['public_key_ed'] = $codec->decodeNodePublic($v->validation_public_key)->toString(); //ED
        $validators[$v->validation_public_key]['public_key'] = $v->validation_public_key; //nX
        $validators[$v->validation_public_key]['address'] = CoreUtilities::deriveAddress(\strtoupper($validators[$v->validation_public_key]['public_key_ed'])); //rX
      }

      //Sequence:
      $man = \bin2hex(\base64_decode($v->manifest));
      $validators[$v->validation_public_key]['sequence'] = (int)\substr($man,2,8);
    }
    $validators = \array_values($validators);
    
    return response()->json([
      'url' => $url,
      'updated' => now(),
      'expiration' => $data->expiration,
      'sequence' => $data->sequence,
      'public_key' => $raw->public_key,
      'version' => $raw->version,
      'validators' => $validators
    ]);
  }
}
