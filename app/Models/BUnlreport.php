<?php

namespace App\Models;
#use Illuminate\Support\Facades\DB;
use App\Repository\UnlreportsRepository;

class BUnlreport extends B
{
  protected $table = 'unlreports';
  public $timestamps = false;
  protected $primaryKey = 'first_l';
  protected $keyType = 'integer';
  public string $repositoryclass = UnlreportsRepository::class;

  public $fillable = [
    'first_l', //Primary Key
    'last_l',
    'vlkey',
    'validators'
  ];

  protected $casts = [
    'validators' => 'array',
  ];

  const BQCASTS = [
    'first_l' => 'INTEGER',
    'last_l'  => 'INTEGER',
    'vlkey' => 'STRING',
    'validators' => 'RECORD',
    'validators.pk' => 'STRING',
    'validators.acc' => 'STRING',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'first_l = """'.$this->first_l.'"""';
  }

  public static function find(int $ledger_index, ?string $select = null): ?self
  {
    $data = UnlreportsRepository::fetchByLedgerIndex($ledger_index,$select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function last(?string $select = null): ?self
  {
    $data = UnlreportsRepository::fetchLastRow($select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function insert(array $values): bool
  {
    return UnlreportsRepository::insert($values);
  }

}