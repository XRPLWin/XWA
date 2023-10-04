<?php

namespace App\Models;
#use Illuminate\Support\Facades\DB;
use App\Repository\UnlvalidatorsRepository;
use Illuminate\Support\Collection;

class BUnlvalidator extends B
{
  protected $table = 'unlvalidators';
  public $timestamps = false;
  protected $primaryKey = 'validator';
  protected $keyType = 'string';
  const repositoryclass = UnlvalidatorsRepository::class;

  public $fillable = [
    'validator', //Primary Key
    'account',
    'first_l',
    'active_fl_count'
  ];

  protected $casts = [
    //'validators' => 'array',
  ];

  const BQCASTS = [
    'validator' => 'STRING',
    'account'  => 'STRING',
    'first_l' => 'INTEGER',
    'active_fl_count' => 'INTEGER',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'validator = """'.$this->validator.'"""';
  }

  public static function find(int $validator, ?string $select = null): ?self
  {
    $data = UnlvalidatorsRepository::fetchByValidator($validator,$select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function fetchAll(?string $select = null): Collection
  {
    $data = UnlvalidatorsRepository::fetchAll($select);
    $models = [];
    foreach($data as $v) {
      $models[] = self::hydrate([$v])->first();
    }
    return collect($models);
  }

  public static function insert(array $values): ?BUnlreport
  {
    $saved = UnlvalidatorsRepository::insert($values);
    if($saved)
      return self::hydrate([$values])->first();
    return null;
  }

}