<?php

namespace App\Models;

#use Illuminate\Support\Collection;
#use Thiagoprz\CompositeKey\HasCompositeKey;
#use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Collection;

class BHookTransaction extends B
{
  use HasUuids;

  protected $table = 'hook_transactions';
  public $timestamps = false;
  #protected $primaryKey = 'id';
  #protected $primaryKey = ['hook', 'h'];
  protected $keyType = 'string';
  #public $incrementing = true;

  public static function getRepository(): string
  {
    if(config('xwa.database_engine') == 'bigquery')
      return \App\Repository\Bigquery\HookTransactionsRepository::class;
    else
      return \App\Repository\Sql\HookTransactionsRepository::class;
  }

  public $fillable = [
    'hook',
    //'h',
    'ctid',
    //'l',
    //'li',
    't',
    'r',
    'txtype',
    'tcode',

    //hookaction:
    //0 execution, 1 create, 2 destroy, 3 install, 4 uninstall, 5 modify, 34 installed but later uninstalled
    // Find accounts with active hooks hookaction=3
    // Find accounts who used hook in the past but not anymore: hookaction=34
    'hookaction',
    'hookresult', //0 => 'UNSET', 1 => 'WASM_ERROR',2 => 'ROLLBACK',3 => 'ACCEPT'
  ];

  protected $casts = [
    't' => 'datetime',
    'ctid' => 'string',
  ];

  const BQCASTS = [
    'hook'        => 'STRING',
    //'h'         => 'STRING',
    'ctid'         => 'INTEGER', //Database: INTEGER, PHP internal: string (uint64)
    //'l'           => 'INTEGER',
    //'li'          => 'INTEGER',
    't'           => 'TIMESTAMP',
    'r'           => 'STRING',
    'txtype'      => 'STRING',
    'tcode'       => 'STRING',
    'hookaction'  => 'INTEGER',
    'hookresult'  => 'INTEGER',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'id="""'.$this->id.'"""'; //uuid
  }

  public static function repo_fetch(?array $select,array $AND, array $orderBy, int $limit = 1, int $offset = 0): Collection
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
}
