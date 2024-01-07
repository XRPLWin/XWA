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

  public function changePrimaryKey(array $key): self
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
  }

  /*public static function getUInt64(string $subject, string $identifier, Carbon $day, string $default = '0'): string
  {
    $r = self::select('value_uint64')->where('subject',$subject)
      ->where('identifier',$identifier)
      ->where('day',$day)
      ->first();
    if(!$r)
      return $default;
    return $r->value_uint64;
  }

  public static function saveUInt64(string $subject, string $identifier, Carbon $day, string $value_UInt64): void
  {
    $t = self::where('subject',$subject)
      ->where('identifier',$identifier)
      ->where('day',$day)
      ->first();
    if(!$t) {
      $t = new self;
      $t->subject = $subject;
      $t->identifier = $identifier;
      $t->day = $day;
    }
    $t->value_uint64 = $value_UInt64;
    $t->save();
  }

  public static function UInt64Increment(string $subject, string $identifier, Carbon $day, int $increment = 1): void
  {
    $t = self::where('subject',$subject)
      ->where('identifier',$identifier)
      ->where('day',$day)
      ->first();
    if(!$t) {
      $t = new self;
      $t->subject = $subject;
      $t->identifier = $identifier;
      $t->day = $day;
      $t->value_uint64 = 0;
      $t->save();
    }
    $t->increment('value_uint64',$increment);
  }*/
}
