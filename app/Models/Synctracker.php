<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Synctracker extends Model
{
  public $timestamps = true;
  /**
   * The attributes that should be cast.
   *
   * @var array
   */
  protected $casts = [
    'is_completed' => 'boolean',
    'last_lt' => 'datetime',
  ];

  public function isCompleted(): bool
  {
    if($this->last_synced_l == $this->last_l && $this->is_completed)
      return true;
    return false;
  }
}
