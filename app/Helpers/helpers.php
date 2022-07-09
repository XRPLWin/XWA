<?php

if (!function_exists('config_static')) {
  /**
  * For claim this site domain verification and other
  */
  function config_static($namespace) : array
  {
    return include base_path().'/config_static/'.$namespace.'.php';
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

if (!function_exists('xrpl_has_flag')) {
  /**
  * Check if $check is included in $flags using bitwise-and operator.
  */
  function xrpl_has_flag(int $flags, int $check)
  {
  	return ($flags & $check) ? true : false;
  }
}

if (!function_exists('wallet_to_short')) {
  /**
  * Shortify wallet address to xxxx....xxxx
  */
  function wallet_to_short($wallet)
  {
    return substr($wallet,0,4).'....'.substr($wallet,-4,4);
  }
}

if (!function_exists('drops_to_xrp')) {
  /**
  * Shortify wallet address to xxxx....xxxx
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
  * Examples: USD,EUR,534F4C4F00000000000000000000000000000000
  * @return string
  */
  function xrp_currency_to_symbol($currencycode) : string
  {
    if( \strlen($currencycode) == 40 )
    {
      $r = \trim(\hex2bin($currencycode));
      return preg_replace('/[\x00-\x1F\x7F]/', '', $r); //remove first 32 ascii characters and \x7F https://en.wikipedia.org/wiki/Control_character
    }
    return $currencycode;
  }
}

if (!function_exists('xw_number_format')) {

  function xw_number_format($decimalnumber) {

    if(\Str::contains((string)$decimalnumber,'.'))
      return rtrim(rtrim($decimalnumber,0),'.');

    return $decimalnumber;
  }
}


if (!function_exists('format_with_suffix')) {
  /**
  * For claim this site domain verification and other
  */
  function format_with_suffix(int $number)
  {
    $suffixes = array('', 'k', 'm', 'B', 'T', ' quad', ' quint', ' sext', ' sept');
    $suffixIndex = 0;

    while(abs($number) >= 1000 && $suffixIndex < sizeof($suffixes))
    {
        $suffixIndex++;
        $number /= 1000;
    }

    return (
        $number > 0
            // precision of 3 decimal places
            ? round((floor($number * 1000) / 1000),1)
            : ceil($number * 1000) / 1000
        )
        . $suffixes[$suffixIndex];
  }
}

if (!function_exists('getbaseurlfromlink')) {
  /**
  * For claim this site domain verification and other
  */
  function getbaseurlfromlink(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['scheme'] . '://' . $url_info['host'];
  }
}

if (!function_exists('getbasedomainfromlink')) {
  /**
  * For claim this site domain verification and other
  * https://xrpl.win -> xrpl.win
  * https://beta.xrpl.win -> beta.xrpl.win
  * beta.xrpl.win -> beta.xrpl.win
  * xrpl.win -> xrpl.win
  */
  function getbasedomainfromlink(string $url)
  {
    $url_info = parse_url($url);
    return $url_info['host'];
  }
}
