<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ledgerindextime extends Model
{
  public $timestamps = false;
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'day_start' => 'date',
  ];
}
