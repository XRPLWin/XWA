<?php

namespace App\Repository\Sql;

use App\Models\BAccount;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccountsRepository extends Repository
{
  /**
   * Load account data by address.
   * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/bigquery/api/src
   * @return ?array
   */
  public static function fetchByAddress(string $address, bool $lockforupdate = false): ?array
  {
    $r = DB::table('accounts')
      ->select([
        'address',
        'l',
        'li',
        'lt',
        'activatedBy',
        'isdeleted'
      ])
      ->where('address',$address);
      if($lockforupdate) {
        $r = $r->lockForUpdate()->get();
      } else {
        $r = $r->get();
      }
      if(!$r->count()) return null;

      return (array)$r->first();
  }

  /**
   * @deprecated
   */
  public static function getFirstTransactionAllInfo(string $address): array
  {
    $initialData = self::AccountsRepositoryFetchLedgerFirstTransactionAllInfoInitial($address);
    $collection = [];
    //$initialData = null; //test

    //search for first info in all sharded databases:
    if(\is_array($initialData)) {
      $shards = transactions_shard_period($initialData['latestT']);
      $collection = $initialData['data'];
    }
    else
      $shards = transactions_shard_period();

    $TYPES = \array_keys(config('xwa.transaction_types'));
    $TYPES_USED = \array_keys($collection);
    $TYPES = \array_diff($TYPES,$TYPES_USED);
    
    foreach($shards as $ym) {
      foreach($TYPES as $_type) {
        //echo $ym.' '.$_type.'<br>';
        if(!isset($collection[$_type])) {
          $row = DB::table(transactions_db_name($ym))->select('t')
            ->where('address',$address)
            ->where('xwatype',$_type)
            ->orderBy('l','asc')
            ->first();

          if($row) $collection[$_type] = Carbon::parse($row->t)->format('U');
        }
      }
      
      $TYPES_USED = \array_keys($collection);
      $TYPES = \array_diff($TYPES,$TYPES_USED);
      /*$results = DB::table(transactions_db_name($ym))->select('xwatype',DB::raw('MIN(`t`) as t'))
        ->where('address',$address)
        ->orderBy('t','asc')
        ->groupBy('xwatype')
        ->get();

      foreach($results as $row) {
        if(!isset($collection[$row->xwatype]))
          $collection[$row->xwatype] = Carbon::parse($row->t)->format('U');
      }*/
    }
    //exit;
    return $collection;


    //OLD BELOW:
    /*$results = DB::table('transactions')->select('xwatype',DB::raw('MIN(`t`) as t'))
      ->where('address',$address)
      ->orderBy('t','asc')
      ->groupBy('xwatype')
      ->get();

    $collection = [];
    foreach($results as $row) {
      $collection[$row->xwatype] = Carbon::parse($row->t)->format('U');
    }
    return $collection;*/
  }

  /**
   * @return ?int UNIX TIMESTAMP
   * @throws \Throwable
   */
  static function getFirstTransactionTime(string $address): ?int
  {
    $XRPLClient = app(\XRPLWin\XRPL\Client::class);
    $account_tx = $XRPLClient->api('account_tx')
      ->params([
        'account' => $address,
        //'ledger_index' => 'current',
        'ledger_index_min' => -1, //earliest available
        'ledger_index_max' => -1, //latest available
        'binary' => false,
        'forward' => true,
        'limit' => 1, //1
      ]);

    try {
      $account_tx->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
      throw $e;
    }

    if(!$account_tx->isSuccess()) {
      throw new \Exception('non success response in getFirstTransactionTime for '.$address);
    }

    $txs = $account_tx->finalResult();

    foreach($txs as $tx) {
      return ripple_epoch_to_carbon((int)$tx->tx->date)->format('U');
    }
    return null;
  }

  /**
   * AccountsRepositoryFetchLedgerFirstTransactionAllInfoInitial
   * Fetches first batch (max 400) account transactions, parses them and collects
   * first types, those are initial values for getFirstTransactionAllInfo() method.
   * This will help skip checks on sharded transaction tables when unndeded.
   * This mehod is faster than checking ALL sharded tables.
   * If ledger transaction fails do nothing just skip to standard full shard scan.
   * @return ?array
   * @deprecated
   */
  protected static function AccountsRepositoryFetchLedgerFirstTransactionAllInfoInitial(string $address): ?array
  {
    $r = [];
    $latestT = 0;
    $XRPLClient = app(\XRPLWin\XRPL\Client::class);
    $account_tx = $XRPLClient->api('account_tx')
      ->params([
        'account' => $address,
        //'ledger_index' => 'current',
        'ledger_index_min' => -1, //earliest available
        'ledger_index_max' => -1, //latest available
        'binary' => false,
        'forward' => true,
        'limit' => 400, //400
      ]);

    try {
      $account_tx->send();
    } catch (\XRPLWin\XRPL\Exceptions\XWException $e) {
      //do nothing (skip)
    }

    if(!$account_tx->isSuccess()) {
      return null;
    }

    $txs = $account_tx->finalResult();

    foreach($txs as $tx) {
      $latestT = ripple_epoch_to_carbon((int)$tx->tx->date)->format('U');
      
      if($tx->meta->TransactionResult != 'tesSUCCESS') continue;

      try {
        $parser = \App\XRPLParsers\Parser::get($tx->tx, $tx->meta, $address);
      } catch (\Throwable $e) {
        throw $e;
      }
      //$parsedData = $parser->toBArray();

      if($parser->getPersist() === false) continue;

      $TransactionClassName = '\\App\\Models\\BTransaction'.$parser->getTransactionTypeClass();
      $TYPE = $TransactionClassName::TYPE;
      if(!isset($r[$TYPE])) {
        $r[$TYPE] = ripple_epoch_to_carbon((int)$parser->getDataField('Date'))->format('U');
      }

      # Activations
      $activatedAddresses = $parser->getActivated();
      foreach($activatedAddresses as $activatedAddress) {
        $TYPE = \App\Models\BTransactionActivation::TYPE;
        if(!isset($r[$TYPE])) {
          $r[$TYPE] = ripple_epoch_to_carbon((int)$parser->getDataField('Date'))->format('U');
          break;
        }
      }
    }
    return ['latestT' => (int)$latestT, 'data' => $r];
  }

}