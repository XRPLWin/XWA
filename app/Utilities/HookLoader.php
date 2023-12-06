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
    string $hookon,
    array $params,
    string $namespace
    ): BHook
  {
    $HookModel = self::get($hook,$l_from);
  
    if(!$HookModel)
    {
      $HookModel = new BHook([
        'hook' => $hook,
        'txid' => $txid,
        'l_from' => $l_from,
        'l_to' => 0, //0 = not deleted
        'hookon' => $hookon,
        'params' => $params,
        'namespace' => $namespace,
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
    string $hookon,
    array $params,
    string $namespace
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
        'hookon' => $hookon,
        'params' => $params,
        'namespace' => $namespace,
        'title' => '',
        'descr' => '',
      ]);
      $HookModel->save(); //this should be save or update
    }
    DB::commit();

    Cache::tags(['hook'.$hook])->delete('dhook:'.$hook.'_'.$l_from);

    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return ?BHook
   */
  public static function get(string $hook, int $l_from, bool $lockforupdate = false): ?BHook
  {
    //$HookArray = Cache::get('dhook:'.$hook.'_'.$l_from);
    $HookArray = Cache::tags(['hook'.$hook])->get('dhook:'.$hook.'_'.$l_from);
    if($HookArray == null) {
      $HookModel = BHook::repo_find($hook,$l_from,$lockforupdate);
      if(!$HookModel)
        return null;
      Cache::tags(['hook'.$hook])->put('dhook:'.$hook.'_'.$l_from, $HookModel->toArray(), 86400); //86400 seconds = 24 hours
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