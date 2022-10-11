<?php
/**
* XrplWinAnalyzer (XWA) main config file
*/

return [

  //sync this version with composer.json
  'version' => '0.0.1',

  /*
  |--------------------------------------------------------------------------
  | Search cache disk
  |--------------------------------------------------------------------------
  |
  | Where to store serialized search cache dumps.
  | One of: s3, private, public
  | @see config/filesystems.php
  | Diectory path on disk sample: /searchcachedir/ful/Fullfilename
  | Second part is sub-dir first 3 letters of filename lowercased.
  */
  'searchcachedisk' => env('XWA_SEARCHCACHEDISK', 'private'),
  'searchcachedir' => env('XWA_SEARCHCACHEDIR','searchcachedir'),

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
  | Warning: Changing this value means you need to to clear Redis cache and empty "maps" table.
  */
  'scan_limit' => env('XWA_SCAN_LIMIT', 1000),

  /*
  |--------------------------------------------------------------------------
  | Paginator breakpoint
  |--------------------------------------------------------------------------
  | Default 500
  | How much results one request can pull per txType, before paginating multiple ledger days.
  | This value does not take into account internal per-ledger-day paginating.
  */
  'paginator_breakpoint' => 2000, //500

  
];
