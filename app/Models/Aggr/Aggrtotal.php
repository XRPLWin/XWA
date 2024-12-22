<?php

namespace App\Models\Aggr;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
//use Thiagoprz\CompositeKey\HasCompositeKey;

class Aggrtotal extends Model
{
  //use HasCompositeKey;

  protected $table = 'aggrtotals';
  public $timestamps = false;
  //protected $primaryKey = ['txtype','day'];
  //protected $keyType = 'string';
  //public $incrementing = false;

  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'day' => 'date',
  ];

  public function uniqueIdentifier():string
  {
    return $this->txtype.'_'.$this->day;
  }
}
