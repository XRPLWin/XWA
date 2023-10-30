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
    'server_wss_syncer' => 'ws://185.239.60.22:20400',
    //enable or disable unlreport
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.xrplf.org',
  ],

  'testnet' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 's.altnet.rippletest.net',
    'server_wss_syncer' => 'wss://s.altnet.rippletest.net',
    //enable or disable unlreport
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.altnet.rippletest.net',
  ],

  'xahautest' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau-test.net',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau-test.net',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xahau-test.net',
    'server_wss_syncer' => 'wss://xahau-test.net',
    //enable or disable unlreport
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 6869247, //6869248 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 748958191, //ripple epoch
    'unl_vl' => 'https://vl.test.xahauexplorer.com',
  ],

  'xahau' => [
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau.network',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau.network',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xahau.network',
    'server_wss_syncer' => 'wss://xahau.network',
    //enable or disable unlreport
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 512, //512 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 751985211, //ripple epoch
    'unl_vl' => 'https://vl.xahau.org',
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
