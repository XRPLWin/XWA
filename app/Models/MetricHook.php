<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetricHook extends Model
{
  public $timestamps = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'day' => 'date',
    'is_processed' => 'boolean',
    'hook_ctid' => 'string',
    'ctid_last' => 'string'
  ];
}
