<?php
/**
* XrplWinAnalyzer (XWA) main config file
*/

return [

  //sync this version with composer.json
  'version' => '0.0.1',

  /*
  |--------------------------------------------------------------------------
  | Ledger Moment
  |--------------------------------------------------------------------------
  |
  | One ledger moment consists of eg. 500 ledgers.
  | How granular will data be stored to XWA database.
  |
  */
  //'moment' => 500,

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
  | Paginator breakpoint
  |--------------------------------------------------------------------------
  |
  | How much results one request can pulll per txType, before paginating.
  */
  'paginator_breakpoint' => 500, //500
  

];
