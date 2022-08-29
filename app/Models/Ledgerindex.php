<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledgerindex extends Model
{
  public $table = 'ledgerindexes';
  public $timestamps = false;
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
      'day' => 'date',
  ];

}
