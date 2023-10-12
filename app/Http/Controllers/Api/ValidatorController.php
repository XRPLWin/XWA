<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        $validators[$v->validation_public_key]['public_key_ed'] = $v->validation_public_key;  //EX
        $validators[$v->validation_public_key]['public_key'] = $codec->encodeNodePublic(Buffer::from($v->validation_public_key)); //nX
        $validators[$v->validation_public_key]['address'] = CoreUtilities::deriveAddress(\strtoupper($v->validation_public_key)); //rX
      } else {
        $validators[$v->validation_public_key]['public_key_ed'] = $v->validation_public_key; //nX
        $validators[$v->validation_public_key]['public_key'] = $v->validation_public_key; //nX
        $k = $v->validation_public_key;


        $codec = new AddressCodec();
        $edpubkey = $codec->decodeNodePublic('nHBbiP5ua5dUqCTz5i5vd3ia9jg3KJthohDjgKxnc7LxtmnauW7Z')->toString();
        $rAddress = CoreUtilities::deriveAddress(\strtoupper($edpubkey));
        dd($rAddress);


        $validators[$v->validation_public_key]['address'] = CoreUtilities::deriveAddress(\strtoupper($v->validation_public_key)); //rX
        dd($v,$validators[$v->validation_public_key] );
      }
     
      //$man = \bin2hex(\base64_decode($v->manifest));
      //dd($validators[$v->validation_public_key],$man);
    }
    //$test = CoreUtilities::deriveAddress(\strtoupper('ED1E88D64F134456B4BCBBC5554FDE292CCF8585DED2CAADAF83A499B4276BE312')); //get rAddress! from Ed25519
    //$test2 = $codec->encodeNodePublic(Buffer::from('ED1E88D64F134456B4BCBBC5554FDE292CCF8585DED2CAADAF83A499B4276BE312'));
    //ED1E88D64F134456B4BCBBC5554FDE292CCF8585DED2CAADAF83A499B4276BE312 to nHB2tHvDXE2GM3Cp9ivyAXU3NDLkf8mzYREQkcZ7wFJyBiaVLu24 ?
    //$test = CoreUtilities::deriveAddress(\strtoupper('ED1E88D64F134456B4BCBBC5554FDE292CCF8585DED2CAADAF83A499B4276BE312'));
    //dd($test,$test2);
    //dd($url ,$data, \base64_decode($data->validators[0]->manifest));
    //dd($data);
    //dd($raw,$data);
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
