<?php

/**
* @see https://xrpl.org/public-servers.html
*/
return [

  'net' => env('XRPL_NET', 'mainnet'),

  /**
   * Custom endpoint, configurable via ENV variables
   */
  'custom' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 1),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 0), //ripple epoch (2013-Jan-01 03:21:10.000000000 UTC)

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => env('XRPL_NET_RIPPLED_SERVER_URI', 'http://localhost:51234') ,
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => env('XRPL_NET_RIPPLED_FULLHISTORY_SERVER_URI', 'http://localhost:51234'),
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => env('XRPL_NET_SERVER_WSS', 'localhost:6006'),
    'server_wss_syncer' => [env('XRPL_NET_SERVER_WSS_SYNCER', 'ws://localhost:6006')],
    'networkid' => (int)env('XRPL_NET_NETWORKID', 0),
    //enable or disable unlreport
    'feature_unlreport' => env('XRPL_NET_FEATURE_UNLREPORT', false),
    'feature_unlreport_first_flag_ledger' => env('XRPL_NET_FEATURE_UNLREPORT_FIRST_FLAG_LEDGER', 512),
    'feature_unlreport_first_flag_ledger_close_time' => env('XRPL_NET_FEATURE_UNLREPORT_FIRST_FLAG_LEDGER_CLOSE_TIME', 0),
    'unl_vl' => env('XRPL_NET_UNL_VL', 'http://vl.test'),
    'api_xrpscan' => env('XRPL_NET_API_XRPSCAN', null),
    'api_tokens' => null,
  ],

  'mainnet' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 32570),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 410325670), //ripple epoch (2013-Jan-01 03:21:10.000000000 UTC)

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'http://s1.ripple.com:51234',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xrplcluster.com',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xrplcluster.com',
    //'server_wss_syncer' => ['ws://185.239.60.22:20400','ws://185.239.60.22:20400'],
    //'server_wss_syncer' => ['ws://185.239.60.22:20400'],
    'server_wss_syncer' => ['wss://xrplcluster.com'],
    //'server_wss_syncer' => ['wss://s2.ripple.com'],
    'networkid' => 0,
    //enable or disable unlreport
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.xrplf.org',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => 'https://api.xrpldata.com/api/v1/tokens',
  ],

  'testnet' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 41884176),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 750124142),
    
    //for connection via php GuzzleHttp (reporting server)
    #'rippled_server_uri' => 'https://s.altnet.rippletest.net:51234',
    'rippled_server_uri' => 'https://testnet.xrpl-labs.com',
    //for connection via php GuzzleHttp (full history server)
    #'rippled_fullhistory_server_uri' => 'https://s.altnet.rippletest.net:51234',
    'rippled_fullhistory_server_uri' => 'https://testnet.xrpl-labs.com',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'testnet.xrpl-labs.com',
    'server_wss_syncer' => ['wss://testnet.xrpl-labs.com'],
    'networkid' => 1,
    //enable or disable unlreport
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.altnet.rippletest.net',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => null,
  ],

  'devnet' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 4240000),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 761280743),
    
    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://s.devnet.rippletest.net:51234',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://s.devnet.rippletest.net:51234',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 's.devnet.rippletest.net:51233',
    'server_wss_syncer' => ['wss://s.devnet.rippletest.net:51233'],
    'networkid' => 2,
    //enable or disable unlreport
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.devnet.rippletest.net',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => null,
  ],

  'xahautest' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 3),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 728140030),

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau-test.net',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau-test.net',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xahau-test.net',
    'server_wss_syncer' => ['wss://xahau-test.net'],
    'networkid' => 21338,
    //enable or disable unlreport
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 6869247, //6869248 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 748958191, //ripple epoch
    'unl_vl' => 'https://vl.test.xahauexplorer.com',
    'api_xrpscan' => 'https://api.xahscan.com',
    'api_tokens' => null,
  ],

  'xahau' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 3),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 751983661),

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau.network/explorer/xrplwin',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau.network/explorer/xrplwin',
    //websocket domain (example: 'xahau.network')
    'server_wss' => 'xahau.network',
    //'server_wss_syncer' => ['wss://xahau.network'],
    'server_wss_syncer' => ['wss://xahau.network/explorer/xrplwin'],
    'networkid' => 21337,
    //enable or disable unlreport
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 512, //512 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 751985211, //ripple epoch
    'unl_vl' => 'https://vl.xahau.org',
    'api_xrpscan' => 'https://api.xahscan.com',
    'api_tokens' => 'https://api.xahaudata.com/api/v1/tokens',
  ],

  //https://xrpl.org/basic-data-types.html#specifying-time
  'ripple_epoch' => 946684800,
];
