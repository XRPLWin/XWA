<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Map extends Model
{
  public $table = 'maps';
  public $timestamps = false;
  
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
      //'day' => 'date',
  ];

}
