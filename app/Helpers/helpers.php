<?php


if (!function_exists('instanceid')) {
  /**
  * Config on demand, Laravel way.
  * @param string $namespace - dot seperated namespace where first param is config_static/FILE.php
  * @return mixed
  */
  function instanceid()
  {
    return \substr(config('app.key'),7,4);
  }
}


if (!function_exists('config_static')) {
  /**
  * Config on demand, Laravel way.
  * @param string $namespace - dot seperated namespace where first param is config_static/FILE.php
  * @return mixed
  */
  function config_static(string $namespace)
  {
    $ex = \explode('.',$namespace);
    $path = base_path().'/config_static/'.$ex[0].'.php';
    if(!is_file($path))
      return null;
    $data = include $path;
    array_shift($ex);
    return data_get($data,$ex);
  }
}

if (!function_exists('ripple_epoch_to_epoch')) {
  function ripple_epoch_to_epoch(int $ripple_date)
  {
    return $ripple_date + config('xrpl.ripple_epoch');
  }
}

if (!function_exists('ripple_epoch_to_carbon')) {
  function ripple_epoch_to_carbon(int $ripple_date)
  {
    $timestamp = $ripple_date + config('xrpl.ripple_epoch');
    return \Carbon\Carbon::createFromTimestamp($timestamp);
  }
}

if (!function_exists('bqtimestamp_to_carbon')) {
  function bqtimestamp_to_carbon(string $timestamp) {
    return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s.uP',$timestamp);
  }
}

if (!function_exists('transactions_db_name')) {
  /**
   * Returns table name (depending of configuration variables)
   * @param string $yyyymm eg 202303 or $m->format('Ym')
   * @return string
   */
  function transactions_db_name(string $yyyymm): string
  {
    if(config('xwa.database_engine') == 'sql')
      return 'transactions'.$yyyymm;
    return 'transactions';
  }
}

if (!function_exists('transactions_shard_period')) {
  /**
   * Returns array of strings (suffixes) for transactions_db_name() generation.
   * @param int $initialUnixTimestamp - UNIX timestamp from which to start else start from genesis
   * @return array
   */
  function transactions_shard_period(?int $initialUnixTimestamp = null): array
  {
    if(config('xwa.database_engine') == 'sql') {
      if($initialUnixTimestamp === null)
        $startdate = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'));
      else
        $startdate = \Carbon\Carbon::createFromTimestamp($initialUnixTimestamp);
      $period = \Carbon\CarbonPeriod::create($startdate, '30 days', now()->addMonth());
      $r = [];
      foreach($period as $m) {
        $r[] = $m->format('Ym');
      }
      return $r;
    }
    return [];
  }
}

if (!function_exists('xrpl_has_flag')) {
  /**
   * Check if $check is included in $flags using bitwise-and operator.
   * @return bool
   */
  function xrpl_has_flag(int $flags, int $check): bool
  {
  	return ($flags & $check) ? true : false;
  }
}

if (!function_exists('calcPercentFromTwoNumbers')) {
  function calcPercentFromTwoNumbers($num_amount, $num_total,$decimal_places = 3): float {
  	$count1 = $num_amount / $num_total;
  	$count2 = $count1 * 100;
  	$count = number_format($count2, $decimal_places);
    return (float)$count;
  }
}

if (!function_exists('wallet_to_short')) {
  /**
   * Shortify wallet address to xxxx....xxxx
   * @return string
   */
  function wallet_to_short(string $wallet, string $seperator = '....'): string
  {
    return substr($wallet,0,4).$seperator.substr($wallet,-4,4);
  }
}

if (!function_exists('drops_to_xrp')) {
  /**
  * Converts drops to XRP.
  */
  function drops_to_xrp(int $num)
  {
    return $num/1000000;
  }
}

if (!function_exists('xrp_currency_to_symbol')) {
  /**
  * Decode HEX XRPL currency to symbol.
  * If already symbol returns that symbol (checked by length).
  * Examples: USD,EUR,534F4C4F00000000000000000000000000000000,LP 031234...
  * @return string
  */
  function xrp_currency_to_symbol(string $currencycode, string $malformedUtf8ReturnString = '?') : string
  {
    return \XRPLWin\XRPL\Utilities\Util::currencyToSymbol($currencycode, $malformedUtf8ReturnString);

    /*if( \strlen($currencycode) == 40 )
    {
      if(\substr($currencycode,0,2) == '03') {
        //AMM LP token, 03 + 19 bytes of sha512
        return 'LP '.$currencycode;
      }
      $r = \trim(\hex2bin($currencycode));
      $r = preg_replace('/[\x00-\x1F\x7F]/', '', $r); //remove first 32 ascii characters and \x7F https://en.wikipedia.org/wiki/Control_character
      if(preg_match('//u', $r)) //This will will return 0 (with no additional information) if an invalid string is given.
        return $r;
      return $malformedUtf8ReturnString; //malformed UTF-8 string
    }
    return $currencycode;*/
  }
}

if (!function_exists('xw_number_format')) {

  function xw_number_format($decimalnumber) {

    if(\Str::contains((string)$decimalnumber,'.'))
      return rtrim(rtrim((string)$decimalnumber,'0'),'.');

    return $decimalnumber;
  }
}


if (!function_exists('format_with_suffix')) {
  /**
  * For claim this site domain verification and other
  */
  function format_with_suffix(mixed $number)
  {
    $number_orig = $number;
    $suffixes = array('', 'k', 'm', 'B', 'T', ' quad', ' quint', ' sext', ' sept');
    $suffixIndex = 0;

    while(abs($number) >= 1000 && $suffixIndex < sizeof($suffixes))
    {
        $suffixIndex++;
        $number /= 1000;
    }

    if(!isset($suffixes[$suffixIndex]))
      return $number_orig;

    return (
        $number > 0
            // precision of 3 decimal places
            ? round((floor($number * 1000) / 1000),1)
            : ceil($number * 1000) / 1000
        )
        . $suffixes[$suffixIndex];
  }
}

if (!function_exists('getbaseurlfromurl')) {
  /**
  * For claim this site domain verification and other
  */
  function getbaseurlfromurl(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['scheme'] . '://' . $url_info['host'];
  }
}

if (!function_exists('getbasedomainfromurl')) {
  /**
  * For claim this site domain verification and other
  * https://xrpl.win -> xrpl.win
  * https://beta.xrpl.win -> beta.xrpl.win
  * beta.xrpl.win -> beta.xrpl.win
  * xrpl.win -> xrpl.win
  */
  function getbasedomainfromurl(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['host'];
  }
}

if (!function_exists('validateXRPAddressOrFail')) {
  /**
  * Validates XRP Address or throw exception.
  * @throws Symfony\Component\HttpKernel\Exception\HttpException
  */
  function validateXRPAddressOrFail(mixed $address): void
  {
    if(!isValidXRPAddressFormat($address))
      abort(422, 'XRP address format is invalid');
  }
}

if (!function_exists('isValidXRPAddressFormat')) {
  /**
  * Validates XRP Address or throw exception.
  * @throws Symfony\Component\HttpKernel\Exception\HttpException
  */
  function isValidXRPAddressFormat(mixed $address): bool
  {
    $validator = \Illuminate\Support\Facades\Validator::make(['address' => $address], [
      'address' => ['string',  new \App\Rules\XRPAddress],
    ]);
    if ($validator->fails())
      return false;
    return true;
  }
}

if (!function_exists('memory_get_usage_formatted')) {
  function memory_get_usage_formatted()
  {
    $size = memory_get_usage(true);
    $unit=array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
  }
}

if (!function_exists('stringDecimalX10000')) {
  /**
   * Converts 123456.079 to 1234560790
   */
  function stringDecimalX10000(string $num): int
  {
    $ex = \explode('.',$num);
    if(!isset($ex[1])) $ex[1] = '0';
    $r = $ex[0].\str_pad($ex[1],4,'0',STR_PAD_RIGHT);
    return $r;
  }
}

/**
 * CTID
 * @see https://github.com/XRPLF/CTID/blob/main/ctid.php
 */
if (!function_exists('encodeCTID')) {

  /**
   * @param int $ledger_index
   * @param int $txn_index
   * @param int $network_id
   * @return string eg. C50CDE1500380000
   */
  function encodeCTID(int $ledger_index, int $txn_index, int $network_id): string
  {
    return \XRPL_PHP\Core\Ctid::fromRawValues($ledger_index,$txn_index,$network_id)->getHex();
  }
}
/*echo PHP_INT_MAX;
$val = "18446744073709551614";
$maxUnsign64Bit ="18446744073709551615";
if(ctype_digit($val) AND bccomp($val,$maxUnsign64Bit) !== 1){
  echo 'is a 64 Bit number';
};
exit;*/

if (!function_exists('decodeCTID')) {

  /**
   * @param string $ctidhex
   * @return array [ledger_index => int, txn_index => int, network_id => int]
   */
  function decodeCTID(string $ctidhex): array
  {
    $ctidinstance = new \XRPL_PHP\Core\Ctid($ctidhex);
    return [
      'ledger_index' => $ctidinstance->getLedgerIndex(),
      'txn_index' => $ctidinstance->getTransactionIndex(),
      'network_id' => $ctidinstance->getNetworkId(),
    ];
  }
}

if (!function_exists('bchexdec')) {
  /**
   * hexdec but suppported uint64 numbers
   */
  function bchexdec(string $hex): string
  {
    $dec = 0;
    $len = strlen($hex);
    for ($i = 1; $i <= $len; $i++) {
      $dec = bcadd((string)$dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
    }
    return $dec;
  }
}
if (!function_exists('bcdechex')) {
  /**
   * dechex but suppported uint64 numbers
   */
  function bcdechex(string $dec): string
  {
    $last = bcmod($dec, 16);
    $remain = bcdiv(bcsub($dec, $last), 16);
    if($remain == 0) {
      $r = dechex($last);
    } else {
      $r = bcdechex($remain).dechex($last);
    }
    return \strtoupper($r);
  }
}
//https://github.com/protocolbuffers/protobuf/pull/14552/files
//var_dump(decodeCTID('C50CDE1500380000'));exit;
//var_dump(encodeCTID(84729365,56,0));exit;

/*
var_dump('C01673490000535A');
$test = \bchexdec('C01673490000535A');
var_dump($test);
$test2 = \bcdechex($test);
var_dump($test2);
exit;
*/

if (!function_exists('toMonthPeriod')) {
  /**
   * dechex but suppported uint64 numbers
   */
  function toMonthPeriod(\Carbon\Carbon $from, \Carbon\Carbon $to): \Carbon\CarbonPeriod
  {
    $from2 = clone $from;
    $from2->firstOfMonth();
    return $from2->toPeriod($to, 1, 'month')->settings(['monthOverflow' => false]);
  }
}