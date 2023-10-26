<?php

namespace App\Models;
use XRPLWin\UNLReportReader\UNLReportReader;
use XRPLWin\XRPL\Utilities\UNLReportFlagLedger;
#use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class BUnlreport extends B
{
  protected $table = 'unlreports';
  public $timestamps = false;
  protected $primaryKey = 'first_l';
  protected $keyType = 'int';
  public $incrementing = false;

  public static function getRepository(): string
  {
    if(config('xwa.database_engine') == 'bigquery')
      return \App\Repository\Bigquery\UnlreportsRepository::class;
    else
      return \App\Repository\Sql\UnlreportsRepository::class;
  }

  public $fillable = [
    'first_l', //Primary Key
    'last_l',
    'vlkey',
    'validators'
  ];

  protected $casts = [
    'validators' => 'array'
  ];

  const BQCASTS = [
    //'first_t' => 'TIMESTAMP',
    'first_l' => 'INTEGER',
    'last_l'  => 'INTEGER',
    'vlkey' => 'STRING',
    'validators' => 'ARRAY',
    //'validators' => 'RECORD',
    //'validators.pk' => 'STRING',
    //'validators.acc' => 'STRING',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'first_l = '.$this->first_l;
  }

  /*public static function find(int $ledger_index, ?string $select = null): ?self
  {
    $data = UnlreportsRepository::fetchByLedgerIndex($ledger_index,$select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }*/

  public static function repo_last(array $select = []): ?self //OK
  {
    $data = self::getRepository()::fetchLastRow($select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function repo_insert(array $values): ?BUnlreport
  {
    
    //revert casted attributes to their raw form:
    $values['validators'] = \json_encode($values['validators']);


    $saved = self::getRepository()::insert($values);
    if($saved) {
      
      return self::hydrate([$values])->first();
    }
    return null;
  }

  public static function repo_fetchByRange(int $start_li, int $end_li)
  {
    $data = self::getRepository()::fetchByLedgerIndexRange($start_li, $end_li);
    return self::expandReports($data, $start_li, $end_li);
  }

  /**
   * @param array $compactedRows - result from bigquery db
   * @return array expanded rows for each flag ledger
   */
  public static function expandReports(array $compactedRows, int $start_li, int $end_li): array
  {
    if(!count($compactedRows))
      return [];

    //Collect variables
    $first_fl = UNLReportFlagLedger::next($start_li); //1 to 256 (+255)
    $last_fl = UNLReportFlagLedger::next($end_li);
    $count = UNLReportReader::calcNumFlagsBetweenLedgers($first_fl,$last_fl);

    $r = [];
    $is_started = false;
    for($x=1; $x<=$count; $x++) {
      $flag = ($first_fl+(256*($x-1)));

      foreach($compactedRows as $row) {
        $row = (array)$row;
        if($row['first_l'] < $flag && $row['last_l'] >= $flag) {
          $is_started = true;
          $row['first_l'] = $flag-255;
          $row['last_l'] = $flag;
          if(\is_string($row['validators'])) {
            $row['validators'] = \json_decode($row['validators']);
          }
            
          $r[$flag] = $row;

          break;
        }
      }
      if(!isset($r[$flag]) && !$is_started) {
        $r[$flag] = [
          'first_l' => $flag-255,
          'last_l' => $flag,
          'vlkey' => '',
          'validators' => [],
        ];
      }
    }
    return \array_values($r);
  }

  /**
   * Converts validators list returned from node to xwa compatible format.
   * @see https://github.com/XRPLWin/UNLReportReader
   * @param array [ [ 'Account' => ?string, 'PublicKey' => string], ... ]
   * @return array [ string, ... ]
   */
  public static function normalizeValidatorsList(array $rawValidatorsList): array
  {
    if(!count($rawValidatorsList))
      return [];

    $normalized = [];
    foreach($rawValidatorsList as $v) {
      $normalized[] = $v['PublicKey'];
      //$normalized[] = [
      //  'pk' => $v['PublicKey'],
      //  'acc' => $v['Account']
      //];
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
      $validators_seed_array[] = (string)$v;
    }
    \sort($validators_seed_array);
    $seed .= \implode('_',$validators_seed_array);
    return \hash('sha512',$seed);
  }

}