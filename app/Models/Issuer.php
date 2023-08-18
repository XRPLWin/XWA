<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Issuer extends Model
{
  public $timestamps = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'is_verified' => 'boolean',
    'is_kyc' => 'boolean',
  ];
}
