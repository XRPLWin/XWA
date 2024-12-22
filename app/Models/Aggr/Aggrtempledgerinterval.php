<?php

namespace App\Models\Aggr;

use Illuminate\Database\Eloquent\Model;

class Aggrtempledgerinterval extends Model
{
  protected $table = 'aggrtempledgerintervals';
  public $timestamps = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    //'total' => 'string',
    'day' => 'date',
  ];
}
