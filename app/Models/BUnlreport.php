<?php

namespace App\Models;
#use Illuminate\Support\Facades\DB;
use App\Repository\UnlreportsRepository;

class BUnlreport extends B
{
  protected $table = 'unlreports';
  public $timestamps = false;
  protected $primaryKey = 'first_l';
  protected $keyType = 'int';
  public string $repositoryclass = UnlreportsRepository::class;

  public $fillable = [
    'first_l', //Primary Key
    'last_l',
    'vlkey',
    'validators'
  ];

  protected $casts = [
    //'validators' => 'array',
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
    return 'first_l = '.$this->first_l;
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

  public static function insert(array $values): ?BUnlreport
  {
    $saved = UnlreportsRepository::insert($values);
    if($saved)
      return self::hydrate([$values])->first();
    return null;
  }

  /**
   * Converts validators list returned from node to xwa compatible format.
   * @see https://github.com/XRPLWin/UNLReportReader
   * @param array [ [ 'Account' => ?string, 'PublicKey' => string], ... ]
   * @return array [ [ 'pk' => string, 'acc' => ?string ], ... ]
   */
  public static function normalizeValidatorsList(array $rawValidatorsList)
  {
    if(!count($rawValidatorsList))
      return [];

    $normalized = [];
    foreach($rawValidatorsList as $v) {
      $normalized[] = [
        'pk' => $v['PublicKey'],
        'acc' => $v['Account']
      ];
    }
    return $normalized;
  }

  /**
   * Generate hash of contents: 'vlkey' and 'validators', used for diff checks.
   * Generated hash should always return same no matter of order of validators.
   * @return string sha512 of seed string
   */
  public function generateHash(): string
  {
    $seed = (string)$this->vlkey.'_';
    $validators_seed_array = [];
    foreach($this->validators as $v) {
      $validators_seed_array[] = $v['pk'].'-'.(string)$v['acc'];
    }
    \sort($validators_seed_array);
    $seed .= \implode('_',$validators_seed_array);
    return \hash('sha512',$seed);
  }

}