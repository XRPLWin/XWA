<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Nftfeed extends Model
{
  use HasUuids;

  protected $table = 'nftfeeds';
  public $timestamps = false;
  //protected $primaryKey = 'ctid';
  //public $incrementing = false;
  protected $keyType = 'string';
  
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'ctid' => 'string',
    'taxon' => 'string',
    't' => 'datetime',
  ];

}