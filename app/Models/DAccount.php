<?php

namespace App\Models;

use App\Jobs\QueueArtisanCommand;
use Illuminate\Support\Facades\DB;
use App\Utilities\Ledger;
use Illuminate\Support\Facades\Cache;
use XRPLWin\XRPL\Client as XRPLWinApiClient;

/**
 * DynamoDB Transaction Account information.
 */
final class DAccount extends DTransaction
{
  protected $primaryKey = 'PK';
  protected $compositeKey = null;

  //No TYPE
  public $fillable = [
    'PK', //Primary Key
    'l',  //Last synced ledger_index
    'by', //Activated by
    't',  //account internal type, undefined - normal, 1 - issuer
  ];

  /**
   * Get the table associated with the model.
   *
   * @return string
   */
  public function getTable(?string $char = null)
  {
    return config('dynamodb.prefix').'accounts';
  }

  public static function boot()
  {
      parent::boot();
      static::saved(function (DAccount $model) {
        $model->flushCache();
      });
      static::deleted(function (DAccount $model) {
        $model->flushCache();
      });
  }

  public function flushCache()
  {
    Cache::forget('daccount:'.$this->PK);
    Cache::forget('daccount_fti:'.$this->PK);
  }

  /**
   * Synces job if not already synced
   * @return bool True if added to sync queue (queued)
   */
  public function sync(bool $recursive = true): bool
  {
    //check if already synced
    $check = DB::connection(config('database.default'))
      ->table('jobs')
      ->where('qtype','account')
      ->where('qtype_data',$this->address)
      ->count();
    
    if($check)
      return false;

    $char = \strtolower(\substr($this->address,1,1)); //rAcct... = 'a', xAcct... = 'a', ...
    if($char !== '') {
      foreach(config('xwa.queue_groups') as $qg => $v) {
        if(in_array($char,$v)) {
          QueueArtisanCommand::dispatch(
            'xwa:accountsync',
            ['address' => $this->address, '--recursiveaccountqueue' => $recursive ],
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
   * Is account synced currently.
   */
  public function isSynced($leeway_ledgers = 1)
  {
    $current_ledger = Ledger::current();
    $l = (int)$this->l;
    $check = $l + $leeway_ledgers;
    if($check <= $current_ledger)
      return false;
    return true;
  }

  /**
   * Is account synced in last 60 ledgers (about 10 minutes).
   * @return bool
   */
  public function isSyncedRecently(): bool
  {
    return $this->isSynced(60);
  }

  /**
   * Queries XRPLedger to determine if this account is issuer.
   * @return bool
   */
  public function checkIsIssuer(): bool
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
  }

  /**
   * @return ?array [ 'repoch' => <ripple epoch>, 'date' => YYYY-MM-DD, 'li' => SK ]
   */
  private function getFirstTransactionInfo(string $txTypeNamepart): ?array
  {
    $result = null;
    $DTransactionModelName = '\\App\\Models\\DTransaction'.$txTypeNamepart;
    $r = $DTransactionModelName::createContextInstance($this->PK)->where('PK', $this->PK.'-'.$DTransactionModelName::TYPE)->where('SK', '>', 0)->take(1)->get(['t'])->first(); //['PK','SK','t']
    if($r) {
      $result = [
        'repoch' => $r->t, //ripple epoch
        'date' => ripple_epoch_to_carbon($r->t)->format('Y-m-d'),
        'li' => $r->SK
      ];
    }
      
    return $result;
  }

  /**
   * Cached
   * @return [ 'first' => YYYY-MM-DD, 'first_per_types' => [ <DTransaction::TYPE> =>  [ repoch, date, li ], ... ] ]
   */
  public function getFirstTransactionAllInfo(): array
  {
    $cache_key = 'daccount_fti:'.$this->PK;
    $r = Cache::get($cache_key);

    if($r === null) {
      $typeList = config('xwa.transaction_types');
      $repoch_tracker = null;
  
      $r = [
        'first' => null, //time of first transaciton
        'first_per_types' => [],   //times of first transaction per types
      ];
  
      foreach($typeList as $typeIdentifier => $typeName) {
        $t = $this->getFirstTransactionInfo($typeName);
        $r['first_per_types'][$typeIdentifier] = $t;
        if($t !== null) {
          if($repoch_tracker  === null) {  //initial
            $repoch_tracker = $t['repoch'];
            $r['first'] = $t['date'];
          }
          if($t['repoch'] < $repoch_tracker) { //check is lesser
            $repoch_tracker = $t['repoch'];
            $r['first'] = $t['date'];
          }
        }
        unset($t);
      }
      unset($repoch_tracker);
      Cache::put( $cache_key, $r, 2629743); //2629743 seconds = 1 month
    }
    return $r;
  }

}
