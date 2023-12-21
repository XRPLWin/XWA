<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tracker extends Model
{
  public $timestamps = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'value_uint64' => 'string'
  ];

  public static function getUInt64(string $subject, string $default = '0'): string
  {
    $r = self::select('value_uint64')->where('subject',$subject)->first();
    if(!$r)
      return $default;
    return $r->value_uint64;
  }

  public static function saveUInt64(string $subject, string $value_UInt64): void
  {
    $t = self::where('subject',$subject)->first();
    if(!$t) {
      $t = new self;
      $t->subject = $subject;
    }
    $t->value_uint64 = $value_UInt64;
    $t->save();
  }

  public static function getInt(string $subject, int $default = 0): int
  {
    $r = self::select('value_int')->where('subject',$subject)->first();
    if(!$r)
      return $default;
    return $r->value_int;
  }

  public static function saveInt(string $subject, int $value_Int): void
  {
    $t = self::where('subject',$subject)->first();
    if(!$t) {
      $t = new self;
      $t->subject = $subject;
    }
    $t->value_int = $value_Int;
    $t->save();
  }
}
