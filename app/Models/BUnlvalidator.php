<?php

namespace App\Models;
#use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use XRPLWin\UNLReportReader\UNLReportReader;

class BUnlvalidator extends B
{
  protected $table = 'unlvalidators';
  public $timestamps = false;
  protected $primaryKey = 'validator';
  protected $keyType = 'string';
  public $incrementing = false;
  #const repositoryclass = UnlvalidatorsRepository::class;

  public static function getRepository(): string
  {
    if(config('xwa.database_engine') == 'bigquery')
      return \App\Repository\Bigquery\UnlvalidatorsRepository::class;
    else
      return \App\Repository\Sql\UnlvalidatorsRepository::class;
  }

  public $fillable = [
    'validator', //Primary Key
    'account',
    'first_l',
    'last_l',
    'current_successive_fl_count',
    'max_successive_fl_count',
    'active_fl_count'
  ];

  protected $casts = [
    //
  ];

  const BQCASTS = [
    'validator' => 'STRING',
    'account'  => 'STRING',
    'first_l' => 'INTEGER',
    'last_l' => 'INTEGER',
    'current_successive_fl_count' => 'INTEGER',
    'max_successive_fl_count' => 'INTEGER',
    'active_fl_count' => 'INTEGER',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'validator = """'.$this->validator.'"""';
  }

  public static function repo_find(string $validator, array $select = []): ?self
  {
    $data = self::getRepository()::fetchByValidator($validator,$select);
    
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  public static function repo_fetchAll(array $select = []): Collection
  {
    $data = self::getRepository()::fetchAll($select);
    $models = [];
    foreach($data as $v) {
      $models[] = self::hydrate([$v])->first();
    }
    return collect($models);
  }

  public static function repo_insert(array $values): ?BUnlreport
  {
    $saved = self::getRepository()::insert($values);
    if($saved)
      return self::hydrate([$values])->first();
    return null;
  }

  /**
   * @param int $max_ledger_index - this is max ledger index statistics will check to
   * Returned values:
   * - reliability - int percent how much was online since first discovered eg 12.558 (3 decimal places)
   * @return array ['reliability' => float percent]
   */
  public function getStatistics(int $max_ledger_index): array
  {
    $r = [
      'reliability' => 0,
      'is_active' => false,
      //'last_active_l' => $this->last_l, //this is last active ledger (flag ledger)
      //'max_successive_fl_count' => $this->max_successive_fl_count
    ];
    $total_flag_ledgers = UNLReportReader::calcNumFlagsBetweenLedgers($this->first_l,$max_ledger_index);
    //dd($this->first_l,$max_ledger_index,$this->active_fl_count,$total_flag_ledgers);
    $r['reliability'] = calcPercentFromTwoNumbers($this->active_fl_count,$total_flag_ledgers,3);

    if($this->last_l == $max_ledger_index)
      $r['is_active'] = true;
      
    //dd($r,$this->first_l,$this->active_fl_count,$total_flag_ledgers);
    //dd('stats');
    return $r;
  }

}