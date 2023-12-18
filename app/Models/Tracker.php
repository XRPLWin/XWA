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
  ];

  public static function getInt(string $subject, int $default = 0): int
  {
    $r = self::select('value_int')->where('subject','aggrhooktx')->first();
    if(!$r)
      return $default;
    return $r->value_int;
  }

  public static function saveInt(string $subject, int $value_int): void
  {
    $t = self::where('subject',$subject)->first();
    if(!$t) {
      $t = new self;
      $t->subject = $subject;
    }
    $t->value_int = $value_int;
    $t->save();
  }
}
