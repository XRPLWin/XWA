<?php

namespace App\Utilities;
use App\Models\BHook;
use App\Models\BHookTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HookLoader
{
  /**
   * Fetches BHook model or creates new in DB.
   * @return BHook
   * @throws \Exception
   */
  public static function getOrCreate(
    string $hook,
    //string $txid,
    string $owner,
    //int $l_from,
    //int $li_from,
    string $ctid,
    string $hookon,
    array $params,
    string $namespace
    ): BHook
  {
    $HookModel = self::get($hook,$ctid);
  
    if(!$HookModel)
    {
      $HookModel = new BHook([
        'hook' => $hook,
        //'txid' => $txid,
        'owner' => $owner, //address
        'ctid_from' => \bchexdec($ctid), //uint64
        'ctid_to' => 0,
        //'l_from' => $l_from,
        //'li_from' => $li_from,
        //'l_to' => 0, //0 = not deleted
        //'li_to' => 0, //0 - reserved to be filled when destroyed
        //'txid_last' => null,
        'hookon' => $hookon,
        'params' => $params,
        'namespace' => $namespace,
        //'title' => '',
        //'descr' => '',
        'stat_active_installs' => 0,
        'stat_installs' => 0,
        'stat_uninstalls' => 0,
        'stat_exec' => 0,
        'stat_exec_rollbacks' => 0,
        'stat_exec_accepts' => 0,
        'stat_exec_other' => 0,
      ]);
      
      $HookModel->save(); //this should be save or update
    }
    return $HookModel;
  }

  /**
   * Fetches BHook model or creates new in DB. Locks it.
   * @return BHook
   * @throws \Exception
   */
  public static function getForUpdateOrCreate(
    string $hook,
    //string $txid,
    string $owner,
    ///int $l_from,
    //int $li_from,
    string $ctid,
    string $hookon,
    array $params,
    string $namespace
  ): BHook
  {
    DB::beginTransaction();
    $HookModel = self::get($hook,$ctid,true);
    
    if(!$HookModel)
    {
      $HookModel = new BHook([
        'hook' => $hook,
        //'txid' => $txid,
        'owner' => $owner, //address
        'ctid_from' => \bchexdec($ctid), //uint64
        'ctid_to' => 0,
        //'l_from' => $l_from,
        //'li_from' => $li_from,
        //'l_to' => 0, //0 = not deleted yet
        //'li_to' => 0, //0 - reserved to be filled when destroyed
        //'txid_last' => null,
        'hookon' => $hookon,
        'params' => $params,
        'namespace' => $namespace,
        //'title' => '',
        //'descr' => '',
        'stat_active_installs' => 0,
        'stat_installs' => 0,
        'stat_uninstalls' => 0,
        'stat_exec' => 0,
        'stat_exec_rollbacks' => 0,
        'stat_exec_accepts' => 0,
        'stat_exec_other' => 0,
        //'stat_fee_min' => 0,
        //'stat_fee_max' => 0,

      ]);
      $HookModel->save(); //this should be save or update
    }
    DB::commit();

    Cache::tags(['hook'.$hook])->delete('dhook:'.$hook.'_'.$ctid);

    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return ?BHook
   */
  public static function get(string $hook, string $ctid, bool $lockforupdate = false): ?BHook
  {
    //$HookArray = Cache::get('dhook:'.$hook.'_'.$l_from);
    $HookArray = Cache::tags(['hook'.$hook])->get('dhook:'.$hook.'_'.$ctid);
    
    if($HookArray == null) {
      $HookModel = BHook::repo_find($hook,$ctid,$lockforupdate);
      if(!$HookModel)
        return null;
      Cache::tags(['hook'.$hook])->put('dhook:'.$hook.'_'.$ctid, $HookModel->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $HookModel = BHook::hydrate([$HookArray])->first();
    }
    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return Collection
   */
  public static function getByHash(string $hookhash): Collection
  {
    $hooks = BHook::repo_fetch(null,[['hook', $hookhash]],['ctid_from','desc'],1000,0);
    return $hooks;
  }

  public static function getClosestByHash(string $hook, string $ctid): ?BHook
  {
    //$ctid64 = bchexdec($ctid);
    $ctid64BN = \Brick\Math\BigInteger::of(bchexdec($ctid));
    $hooks = self::getByHash($hook);
    $hookDef = null;
    
    foreach($hooks as $h) {

      $fromBN = \Brick\Math\BigInteger::of($h->ctid_from);
      
      if($h->ctid_to == 0) {

        
        //dd($hooks,$ctid64,$BN);
        
        if($fromBN->isLessThanOrEqualTo($ctid64BN)) {
        //if($h->l_from <= $ledger_index) { //140531 <= 141767
          $hookDef = $h;
          break;
        }
      } else {
        $toBN = \Brick\Math\BigInteger::of($h->ctid_to);

        if($fromBN->isLessThanOrEqualTo($ctid64BN) && $toBN->isGreaterThanOrEqualTo($ctid64BN)) {
        //if($h->l_from <= $ledger_index && $h->l_to >= $ledger_index) {
          $hookDef = $h;
          break;
        }
      }
    }
    return $hookDef;
  }

  /*public static function getTransactionLastByAccountAction(string $hook, string $account, int $hookaction): ?BHookTransaction
  {
    return BHookTransaction::repo_fetch_last_by_account_action($hook,$account,$hookaction);
  }*/

  /**
   * Fetches transaction from hook_transactions table
   * @return Collection (or paginator?)
   */
  /*public static function getTransactionsTODO(string $hook): Collection
  {

  }*/
}