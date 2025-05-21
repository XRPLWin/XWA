<?php

namespace App\Models;

use Illuminate\Support\Collection;
use Thiagoprz\CompositeKey\HasCompositeKey;
use Illuminate\Support\Facades\Cache;

class BHook extends B
{
  use HasCompositeKey;

  protected $table = 'hooks';
  public $timestamps = false;
  #protected $primaryKey = 'hook';
  protected $primaryKey = ['hook', 'ctid_from'];
  protected $keyType = 'string';
  public $incrementing = false;

  public static function getRepository(): string
  {
    if(config('xwa.database_engine') == 'bigquery')
      return \App\Repository\Bigquery\HooksRepository::class;
    else
      return \App\Repository\Sql\HooksRepository::class;
  }

  public $fillable = [
    'hook', //Primary Key
    //'txid',
    'owner',
    'ctid_from', //Database: BIGINT, PHP internal: string (uint64)
    'ctid_to',   //Database: BIGINT, PHP internal: string (uint64)
    //'l_from',
    //'li_from',
    //'l_to',
    //'li_to',
    //'txid_last',
    'hookon',
    'hookcanemit',
    'params',
    'namespace',
    //'title',
    //'descr',
    'stat_active_installs',
    'stat_installs',
    'stat_uninstalls',
    'stat_exec',
    'stat_exec_rollbacks',
    'stat_exec_accepts',
    'stat_exec_other',
    //'stat_fee_min',
    //'stat_fee_max',
  ];

  protected $casts = [
    //'is_deleted' => 'boolean'
    'params' => 'array',
    'ctid_from' => 'string',
    'ctid_to' => 'string'
  ];

  const BQCASTS = [
    'hook' => 'STRING',
    //'txid' => 'STRING',
    'owner' => 'STRING',
    'ctid_from' => 'INTEGER', //Database: INTEGER, PHP internal: string (uint64)
    'ctid_to' => 'INTEGER',   //Database: INTEGER, PHP internal: string (uint64)
    //'l_from' => 'INTEGER',
    //'li_from' => 'INTEGER',
    //'l_to' => 'INTEGER',
    //'li_to' => 'INTEGER',
    //'txid_last' => 'NULLABLE STRING',
    'hookon'  => 'STRING',
    'hookcanemit'  => 'STRING',
    'params'  => 'STRING', //store json here key value one dimensional array
    'namespace' => 'STRING',
    //'title'  => 'STRING',
    //'descr'  => 'STRING',
    'stat_active_installs' => 'INTEGER',
    'stat_installs' => 'INTEGER',
    'stat_uninstalls' => 'INTEGER',
    'stat_exec' => 'INTEGER',
    'stat_exec_rollbacks' => 'INTEGER',
    'stat_exec_accepts' => 'INTEGER',
    'stat_exec_other' => 'INTEGER',
    //'stat_fee_min' => 'INTEGER',
    //'stat_fee_max' => 'INTEGER',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'hook = """'.$this->hook.'""" and ctid_from='.$this->ctid_from;
  }

  public static function boot()
  {
    parent::boot();
    static::saved(function (BHook $model) {
        $model->flushCache();
    });
  }

  public function flushCache()
  {
    Cache::tags(['hook'.$this->hook])->forget('dhook:'.$this->hook.'_'.$this->ctid_from);
  }

  public static function repo_find(string $hook, string $ctid, bool $lockforupdate = false): ?self
  {
    $data = self::getRepository()::fetchByHookHashAndLedgerFrom($hook,$ctid,$lockforupdate);
    
    if($data === null)
      return null;

    return self::hydrate([$data])->first();
  }

  /*public static function repo_fetch(string $hook): Collection
  {
    $data = self::getRepository()::fetchByHookHash($hook);
    return self::hydrate($data);
  }*/

  public static function repo_fetch(?array $select, array $AND, array $orderBy, int $limit = 1, int $offset = 0): Collection
  {
    $data = self::getRepository()::fetch($select,$AND,$orderBy,$limit,$offset);

    if($data === null)
      return collect();

    return self::hydrate($data);
  }

  public static function repo_count(array $AND): int
  {
    return self::getRepository()::count($AND);
  }

  public function getIsActiveAttribute()
  {
    return $this->ctid_to == "0";
  }

}
