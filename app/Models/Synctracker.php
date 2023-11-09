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
  ];

  public function isCompleted(): bool
  {
    if($this->progress_l == $this->last_l && $this->is_completed)
      return true;
    return false;
  }
}
