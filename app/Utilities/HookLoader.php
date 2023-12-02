<?php

namespace App\Utilities;
use App\Models\BHook;
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
    string $txid,
    int $l_from,
    array $params
    ): BHook
  {
    $HookModel = self::get($hook,$txid);
  
    if(!$HookModel)
    {
      $HookModel = new BHook([
        'hook' => $hook,
        'txid' => $txid,
        'l_from' => $l_from,
        'l_to' => 0, //0 = not deleted
        'params' => $params,
        'title' => '',
        'descr' => '',
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
    string $txid,
    int $l_from,
    array $params
  ): BHook
  {
    DB::beginTransaction();
    $HookModel = self::get($hook,$l_from,true);
    
    if(!$HookModel)
    {
      $HookModel = new BHook([
        'hook' => $hook,
        'txid' => $txid,
        'l_from' => $l_from,
        'l_to' => 0, //0 = not deleted yet
        'params' => $params,
        'title' => '',
        'descr' => '',
      ]);
      $HookModel->save(); //this should be save or update
    }
    DB::commit();

    Cache::delete('dhook:'.$hook.'_'.$l_from);

    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return ?BHook
   */
  public static function get(string $hook, int $l_from, bool $lockforupdate = false): ?BHook
  {
    $HookArray = Cache::get('dhook:'.$hook.'_'.$l_from);
    if($HookArray == null) {
      $HookModel = BHook::repo_find($hook,$l_from,$lockforupdate);
      if(!$HookModel)
        return null;
      Cache::put('dhook:'.$hook.'_'.$l_from, $HookModel->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $HookModel = BHook::hydrate([$HookArray])->first();
    }
    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return ?BHook
   */
  public static function getByHash(string $hook): Collection
  {
    $hooks = BHook::repo_fetch($hook);
    return $hooks;
  }
}