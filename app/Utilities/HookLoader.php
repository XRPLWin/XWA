<?php

namespace App\Utilities;
use App\Models\BHook;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HookLoader
{

  /**
   * Fetches BAccount model or creates new in DB.
   * @return BAccount
   * @throws \Exception
   */
  public static function getOrCreate(
    string $hook,
    string $txid,
    int $l_from,
    array $params
    ): BHook
  {
    $HookModel = self::get($hook);
  
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
   * Fetches BAccount model or creates new in DB. Locks it.
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
    $HookModel = self::get($hook,true);
    
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

    Cache::delete('dhook:'.$hook);

    return $HookModel;
  }

  /**
   * Gets BHook from cache or database.
   * @return ?BHook
   */
  public static function get(string $hook, bool $lockforupdate = false): ?BHook
  {
    $HookArray = Cache::get('dhook:'.$hook);
    if($HookArray == null) {
      $HookModel = BHook::repo_find($hook,$lockforupdate);
      if(!$HookModel)
        return null;
      Cache::put('dhook:'.$hook, $HookModel->toArray(), 86400); //86400 seconds = 24 hours
    } else {
      $HookModel = BHook::hydrate([$HookArray])->first();
    }
    return $HookModel;
  }
}