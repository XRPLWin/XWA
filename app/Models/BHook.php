<?php

namespace App\Models;

use Illuminate\Support\Collection;

class BHook extends B
{
  protected $table = 'hooks';
  public $timestamps = false;
  protected $primaryKey = 'hook';
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
    'txid',
    'l_from',
    'l_to',
    'hookon',
    'params',
    'title',
    'descr'
  ];

  protected $casts = [
    //'is_deleted' => 'boolean'
    'params' => 'array'
  ];

  const BQCASTS = [
    'hook' => 'STRING',
    'txid' => 'STRING',
    'l_from' => 'INTEGER',
    'l_to' => 'INTEGER',
    'hookon'  => 'STRING',
    'params'  => 'STRING', //store json here key value one dimensional array
    'title'  => 'STRING',
    'descr'  => 'STRING',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'hook = """'.$this->hook.'"""';
  }

  public static function repo_find(string $hook, int $l_from, bool $lockforupdate = false): ?self
  {
    $data = self::getRepository()::fetchByHookHashAndLedgerFrom($hook,$l_from,$lockforupdate);
    
    if($data === null)
      return null;

    return self::hydrate([$data])->first();
  }

  public static function repo_fetch(string $hook): Collection
  {
    $data = self::getRepository()::fetchByHookHash($hook);
    return self::hydrate($data);
  }

  public function getIsActiveAttribute()
  {
    return $this->l_to === 0;
  }

}
