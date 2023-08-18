<?php

namespace App\Models;

use App\Repository\AccountsRepository;
use App\Repository\TransactionsRepository;

use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;
use Carbon\Carbon;

class BAccount extends B
{
  protected $table = 'accounts';
  protected $primaryKey = 'address';
  protected $keyType = 'string';
  public $timestamps = false;
  public string $repositoryclass = AccountsRepository::class;

  public $fillable = [
    'address', //Primary Key
    'l',
    'li',
    'lt',
    'activatedBy',
    'isdeleted'
  ];

  protected $casts = [
    'lt' => 'datetime',
  ];

  const BQCASTS = [
    'address' => 'STRING',
    'l'       => 'INTEGER',
    'li'      => 'INTEGER',
    'lt'      => 'TIMESTAMP',
    'activatedBy' => 'NULLABLE STRING',
    'isdeleted' => 'BOOLEAN'
  ];

  public static function boot()
  {
      parent::boot();
      static::saved(function (BAccount $model) {
        $model->flushCache();
      });
      static::deleted(function (BAccount $model) {
        $model->flushCache();
      });
  }

  public function flushCache()
  {
    Cache::forget('daccount:'.$this->address);
    Cache::forget('daccount_fti:'.$this->address);
  }

  protected function bqPrimaryKeyCondition(): string
  {
    return 'address = """'.$this->address.'"""';
  }

  public static function find(string $address): ?self
  {
    $data = AccountsRepository::fetchByAddress($address);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function insert(array $values): bool
  {
    return AccountsRepository::insert($values);
  }

  /**
   * Cached
   * Syntax: SELECT xwatype,t FROM `TABLE` WHERE TRUE QUALIFY ROW_NUMBER() OVER (PARTITION BY xwatype ORDER BY t ASC) = 1
   * @return [ 'first' => UNIXTIMESTAMP, 'first_per_types' => [ <DTransaction::TYPE> =>  UNIXTIMESTAMP, ... ] ]
   */
  public function getFirstTransactionAllInfo(): array
  {
    $cache_key = 'daccount_fti:'.$this->address;
    
    $r = Cache::get($cache_key);

    if($r === null) {

      $results = TransactionsRepository::query(
        'SELECT xwatype,t FROM `'.config('bigquery.project_id').'.'.config('bigquery.xwa_dataset').'.transactions` WHERE TRUE QUALIFY ROW_NUMBER() OVER (PARTITION BY xwatype ORDER BY t ASC) = 1'
      );

      $collection = [];
      foreach($results as $row) {
        $collection[$row['xwatype']] = (int)$row['t']->get()->format('U');
      }

      $typeList = config('xwa.transaction_types');
      $timestamp_tracker = null;

      $r = [
        'first' => null, //time of first transaciton
        'first_per_types' => [], //times of first transaction per types
      ];
  
      foreach($typeList as $typeIdentifier => $foo) {
        $r['first_per_types'][$typeIdentifier] = isset($collection[$typeIdentifier]) ? $collection[$typeIdentifier]:null;
        if($r['first_per_types'][$typeIdentifier] !== null) {
          if($timestamp_tracker === null) {  //initial
            $timestamp_tracker = $r['first_per_types'][$typeIdentifier];
            $r['first'] = $r['first_per_types'][$typeIdentifier];
          }
          if($r['first_per_types'][$typeIdentifier] < $timestamp_tracker) { //check is lesser
            $timestamp_tracker = $r['first_per_types'][$typeIdentifier];
            $r['first'] = $r['first_per_types'][$typeIdentifier];
          }
        }
      }
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    return $r;
  }

  /**
   * Queries XRPLedger to determine if this account is issuer.
   * Some public servers disable this API method because it can require a large amount of processing.
   * @return bool
   */
  /*public function checkIsIssuer(): bool
  {
    //get if this account is issuer or not by checking obligations
    $gateway_balances = app(XRPLWinApiClient::class)
        ->api('gateway_balances')
        ->params([
            'account' => $this->address,
            'strict' => true,
            'ledger_index' => 'validated',
        ])
        ->send()
        ->finalResult();

    if(isset($gateway_balances->obligations) && !empty($gateway_balances->obligations))
      return true;

    return false;
  }*/

  /**
   * Synces job if not already synced
   * @param bool $recursive - if true it will queue parent address
   * @param bool $skipcheck - checks if alredy in sync
   * @param int $limit - limit sent to artisan command
   * @return bool True if added to sync queue (queued)
   */
  public function sync(bool $recursive = true, bool $skipcheck = false, int $limit = 0): bool
  {
    if(!$skipcheck) {
      //check if already in sync
      $check = DB::connection(config('database.default'))
        ->table('jobs')
        ->where('qtype','account')
        ->where('qtype_data',$this->address)
        ->count();

      if($check)
        return false;
    }
  

    $char = \strtolower(\substr($this->address,1,1)); //rAcct... = 'a', xAcct... = 'a', ...
    if($char !== '') {
      foreach(config('xwa.queue_groups') as $qg => $v) {
        if(in_array($char,$v)) {
          QueueArtisanCommand::dispatch(
            'xwa:accountsync',
            ['address' => $this->address, '--recursiveaccountqueue' => $recursive, '--limit' => $limit ],
            'account',
            $this->address
          )->onQueue($qg);
          break;
        }
      }
    }
   
    return true;
  }

  /**
   * Is account synced or not depending of input parameters.
   * @return bool
   */
  public function isSynced($leeway_minutes = 1, ?Carbon $referenceTime = null): bool
  {
    if($referenceTime === null)
      $referenceTime = now();
    
    $lt = clone $this->lt;
    
    $lt->addMinutes($leeway_minutes); //10 min leeway time (eg sync can be 10 min stale)

    if($referenceTime->greaterThan($lt))
      return false;
    return true;
  }
}
