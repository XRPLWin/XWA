<?php

/**
* @see https://xrpl.org/public-servers.html
*/
return [

  'net' => env('XRPL_NET', 'mainnet'),

  'mainnet' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'http://s1.ripple.com:51234',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xrplcluster.com',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xrplcluster.com',
    //enable or disable unlreport
    'feature_unlreport' => false,
  ],

  'testnet' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 's.altnet.rippletest.net',
    //enable or disable unlreport
    'feature_unlreport' => false,
  ],

  'xahautest' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau-test.net',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau-test.net',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xahau-test.net',
    //enable or disable unlreport
    'feature_unlreport' => true,
  ],


  //https://xrpl.org/basic-data-types.html#specifying-time
  'ripple_epoch' => 946684800,

  /**
   * Min ledger Analyzer will collect data from
   * You can opt in to less history by adjusting genesis_* values below.
   * Default genesis_ledger             : 32570
   * Default genesis_ledger_close_time  : 410325670
   * @see https://xrpl.org/websocket-api-tool.html#ledger
   * TODO move this to net (see up)
   */
  'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 32570),
  'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 410325670), //ripple epoch (2013-Jan-01 03:21:10.000000000 UTC)

  //'token_source' => 'https://api.xrpldata.com/api/v1/tokens',

];
