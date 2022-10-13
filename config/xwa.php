<?php
/**
 * XRPLWinAnalyzer (XWA) main config file.
 */

return [

  # Sync this version with composer.json
  'version' => '0.0.1',

  /*
  |--------------------------------------------------------------------------
  | Scan limit
  |--------------------------------------------------------------------------
  | Default: 1000
  | How much results maximum DynamoDB Count action will count per query.
  | Put 0 (zero) for no limit - DynamoDB will paginate this when 1MB limit is reached.
  | Smaller limit = more queries and more pages.
  | This is relevant only for rAccount with abnormally large amount of transactions
  | per calendar day, eg. over 15k transactions will yield 4 pages of data with 5000 limit.
  | Warning: Changing this value requires clearing Redis cache and empty "maps" table.
  |   Changing this value requires clearing reverse proxy cache (eg. Varnish, CloudFront ...)
  |   It is recommended not to change this value on production that is already running.
  */
  'scan_limit' => env('XWA_SCAN_LIMIT', 1000),

  /*
  |--------------------------------------------------------------------------
  | Paginator breakpoint
  |--------------------------------------------------------------------------
  | Default: 500
  | How much results one request can pull per txType, before paginating multiple ledger days.
  | This value does not take into account internal per-ledger-day paginating.
  | Max transactions returned per request can be eg. <paginator_breakpoint>*<number of transaction types>
  | for internal (ledger-day) paginating - in short when there is large amount of transactions in one day.
  | Warning: Changing this value requires clearing reverse proxy cache (eg. Varnish, CloudFront ...)
  |   It is recommended not to change this value on production that is already running.
  */
  'paginator_breakpoint' => env('XWA_PAGINATIOR_BREAKPOINT', 500),

  
];
