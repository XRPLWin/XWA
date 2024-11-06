<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
#use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Oracle extends Model
{
  #use HasUuids;
  protected $table = 'oracles';
  public $timestamps = false;
  
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'updated_at' => 'datetime',
  ];

  protected $fillable = [
    'oracle',
    'documentid',
    'provider',
    'base',
    'quote',
    'last_value',
    'updated_at'
  ];

}