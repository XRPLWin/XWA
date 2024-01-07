<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Thiagoprz\CompositeKey\HasCompositeKey;

class RecentAggr extends Model
{
  use HasCompositeKey;

  protected $table = 'recent_aggrs';
  public $timestamps = false;
  protected $primaryKey = ['subject','identifier','day'];
  protected $keyType = 'string';
  public $incrementing = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'value_uint64' => 'string',
    'day' => 'date',
  ];

  /*public function changePrimaryKey(array $key): self
  {
    $this->primaryKey = $key;
    return $this;
  }

  public static function incrementInt(string $subject, string $identifier, Carbon $day, int $value, string $context = '')
  {
    DB::insert('insert into recent_aggrs (subject,identifier,day,value_uint64,context) values (:subject,:identifier,:day,:value_uint64,:context) on duplicate key update value_uint64 = value_uint64+'.$value,[
      'subject' => $subject,
      'identifier' => $identifier,
      'day' => $day->format('Y-m-d'),
      'value_uint64' => $value,
      'context' => $context,
    ]);
  }

  public static function setTo(string $subject, string $identifier, Carbon $day, int $value, string $context)
  {
    DB::insert('insert into recent_aggrs (subject,identifier,day,value_uint64,context) values (:subject,:identifier,:day,:value_uint64,:context) on duplicate key update value_uint64 = :vv, context = :vc',[
      'subject' => $subject,
      'identifier' => $identifier,
      'day' => $day->format('Y-m-d'),
      'value_uint64' => $value,
      'context' => $context,
      'vv' => $value,
      'vc' => $context,
    ]);
  }*/
}
