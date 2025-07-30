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
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'ws://localhost:6006')), //use "," to seperate endpoints
    'networkid' => (int)env('XRPL_NET_NETWORKID', 0),
    //enable or disable features
    'feature_amm' => env('XRPL_NET_FEATURE_AMM', false),
    'feature_oracle' => env('XRPL_NET_FEATURE_ORACLE', false),
    'feature_unlreport' => env('XRPL_NET_FEATURE_UNLREPORT', false),
    'feature_unlreport_first_flag_ledger' => env('XRPL_NET_FEATURE_UNLREPORT_FIRST_FLAG_LEDGER', 512),
    'feature_unlreport_first_flag_ledger_close_time' => env('XRPL_NET_FEATURE_UNLREPORT_FIRST_FLAG_LEDGER_CLOSE_TIME', 0),
    'unl_vl' => env('XRPL_NET_UNL_VL', 'http://vl.test'),
    'api_xrpscan' => env('XRPL_NET_API_XRPSCAN', null),
    'api_tokens' => env('XRPL_NET_API_TOKENS', null),
    'data_api' => env('XRPL_NET_DATA_API', null),
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
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://xrplcluster.com')),
    'networkid' => 0,
    //enable or disable features
    'feature_amm' => true,
    'feature_oracle' => true,
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.xrplf.org',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => 'https://api.xrpldata.com/api/v1/tokens',
    'data_api' => 'https://xrpldata.inftf.org', //'https://data.xrplf.org' -> see https://xrpldata.inftf.org/docs/static/index.html
  ],

  'testnet' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 41884176),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 750124142),
    
    //for connection via php GuzzleHttp (reporting server)
    #'rippled_server_uri' => 'https://s.altnet.rippletest.net:51234',
    'rippled_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //for connection via php GuzzleHttp (full history server)
    #'rippled_fullhistory_server_uri' => 'https://s.altnet.rippletest.net:51234',
    'rippled_fullhistory_server_uri' => 'https://s.altnet.rippletest.net:51234',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 's.altnet.rippletest.net:51233',
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://s.altnet.rippletest.net:51233')),
    'networkid' => 1,
    //enable or disable features
    'feature_amm' => true,
    'feature_oracle' => true,
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.altnet.rippletest.net',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => null,
    'data_api' => null, //'https://testnet.data.xrplf.org',
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
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://s.devnet.rippletest.net:51233')),
    'networkid' => 2,
    //enable or disable features
    'feature_amm' => true,
    'feature_oracle' => true,
    'feature_unlreport' => false,
    'feature_unlreport_first_flag_ledger' => 0,
    'feature_unlreport_first_flag_ledger_close_time' => 0,
    'unl_vl' => 'https://vl.devnet.rippletest.net',
    'api_xrpscan' => 'https://api.xrpscan.com',
    'api_tokens' => null,
    'data_api' => null, //'https://devnet.data.xrplf.org',
  ],

  'xahautest' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 2),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 805537690),

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://xahau-test.net',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://xahau-test.net',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'xahau-test.net',
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://xahau-test.net')),
    'networkid' => 21338,
    //enable or disable features
    'feature_amm' => false,
    'feature_oracle' => false,
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 511, //512 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 805539252, //ripple epoch
    'unl_vl' => 'https://vl.test.xahauexplorer.com',
    'api_xrpscan' => 'https://api.xahscan.com',
    'api_tokens' => null,
    'data_api' => null, //'https://testnet.data.xahau.network',
  ],

  'xahaujshookstestnet' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 12),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 782816340),

    //for connection via php GuzzleHttp (reporting server)
    'rippled_server_uri' => 'https://jshooks.xahau-test.net',
    //for connection via php GuzzleHttp (full history server)
    'rippled_fullhistory_server_uri' => 'https://jshooks.xahau-test.net',
    //websocket domain (example: 'xrplcluster.com')
    'server_wss' => 'jshooks.xahau-test.net',
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://jshooks.xahau-test.net')),
    'networkid' => 31338,
    //enable or disable features
    'feature_amm' => false,
    'feature_oracle' => false,
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 256, //256 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 782817180, //ripple epoch
    'unl_vl' => null, //no unl
    'api_xrpscan' => 'https://api.xahscan.com',
    'api_tokens' => null,
    'data_api' => null, //'https://testnet.data.xahau.network',
  ],

  'xahau' => [
    'genesis_ledger'            => env('XRPL_GENESIS_LEDGER', 3),
    'genesis_ledger_close_time' => env('XRPL_GENESIS_LEDGER_CLOSE_TIME', 751983661),

    //for connection via php GuzzleHttp (reporting server)
    //'rippled_server_uri' => 'https://xahau.network/explorer/xrplwin',
    'rippled_server_uri' => 'https://xahau.network',
    //for connection via php GuzzleHttp (full history server)
    //'rippled_fullhistory_server_uri' => 'https://xahau.network/explorer/xrplwin',
    'rippled_fullhistory_server_uri' => 'https://xahau.network',
    //websocket domain (example: 'xahau.network')
    'server_wss' => 'xahau.network',
    //'server_wss_syncer' => ['wss://xahau.network'],
    //'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://xahau.network/explorer/xrplwinxwa')),
    'server_wss_syncer' => \explode(',',env('XRPL_NET_SERVER_WSS_SYNCER', 'wss://xahau.network')),
    'networkid' => 21337,
    //enable or disable features
    'feature_amm' => false,
    'feature_oracle' => false,
    'feature_unlreport' => true,
    'feature_unlreport_first_flag_ledger' => 512, //512 is first flag
    'feature_unlreport_first_flag_ledger_close_time' => 751985211, //ripple epoch
    'unl_vl' => 'https://vl.xahau.org',
    'api_xrpscan' => 'https://api.xahscan.com',
    'api_tokens' => 'https://api.xahaudata.com/api/v1/tokens',
    'data_api' => 'https://data.xahau.network', //see https://data.xahau.network/docs/static/index.html
  ],

  //https://xrpl.org/basic-data-types.html#specifying-time
  'ripple_epoch' => 946684800,
];
