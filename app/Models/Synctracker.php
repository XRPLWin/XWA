<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Synctracker extends Model
{
  public $timestamps = true;
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'is_completed' => 'boolean',
  ];
}
