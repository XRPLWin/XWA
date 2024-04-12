<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Amm extends Model
{
  protected $table = 'amms';
  protected $primaryKey = 'accountid';
  protected $keyType = 'string';
  public $incrementing = false;
  public $timestamps = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    't' => 'datetime',
    'synced_at' => 'datetime',
  ];

}
