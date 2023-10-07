<?php

namespace App\Models;
#use Illuminate\Support\Facades\DB;
use App\Repository\UnlreportsRepository;
use XRPLWin\UNLReportReader\UNLReportReader;
use XRPLWin\XRPL\Utilities\UNLReportFlagLedger;

class BUnlreport extends B
{
  protected $table = 'unlreports';
  public $timestamps = false;
  protected $primaryKey = 'first_l';
  protected $keyType = 'int';
  const repositoryclass = UnlreportsRepository::class;

  public $fillable = [
    'first_l', //Primary Key
    'last_l',
    //'first_t',
    'vlkey',
    'validators'
  ];

  protected $casts = [
    //'validators' => 'array',
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

  public static function fetchByRange(int $start_li, int $end_li)
  {
    $data = UnlreportsRepository::fetchByLedgerIndexRange($start_li, $end_li);
    return self::expandReports($data, $start_li, $end_li);
  }

  public static function fetchByRangeForValidator(string $validator, int $start_li, int $end_li)
  {
    $where = 'AND EXISTS(SELECT 1 FROM UNNEST(validators) AS v WHERE v="""'.$validator.'""")';
    $data = UnlreportsRepository::fetchByLedgerIndexRange($start_li, $end_li,null,$where);
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
    //$endRow = \end($compactedRows);
    //$last_fl = $endRow['last_l']; //512
    $last_fl = UNLReportFlagLedger::next($end_li);
    //unset($endRow);
    $count = UNLReportReader::calcNumFlagsBetweenLedgers($first_fl,$last_fl);

    $r = [];
    $is_started = false;
    for($x=1; $x<=$count; $x++) {
      $flag = ($first_fl+(256*($x-1)));

      


      //echo $x.' - ('.$first_fl.' - '.$flag.')<br>';
      foreach($compactedRows as $row) {
        if($row['first_l'] < $flag && $row['last_l'] >= $flag) {
          $is_started = true;
          $row['first_l'] = $flag-255;
          $row['last_l'] = $flag;

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
  public static function normalizeValidatorsList(array $rawValidatorsList)
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
      //$validators_seed_array[] = $v['pk'].'-'.(string)$v['acc'];
      $validators_seed_array[] = (string)$v;
    }
    \sort($validators_seed_array);
    $seed .= \implode('_',$validators_seed_array);
    return \hash('sha512',$seed);
  }

}