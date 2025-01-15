<?php

namespace App\Models\Aggr;

use Illuminate\Database\Eloquent\Model;
#use Carbon\Carbon;
#use Illuminate\Support\Facades\DB;

class Aggrobject extends Model
{

  protected $table = 'aggrobjects';
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

  /*public function uniqueIdentifier():string
  {
    return $this->txtype.'_'.$this->day;
  }*/
}