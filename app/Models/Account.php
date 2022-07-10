<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Model;
use Kitar\Dynamodb\Model\Model;

class Account extends Model
{
  protected $table = 'accounts';
  protected $primaryKey = 'Id';
  protected $fillable = ['Id', 'account', 'title'];
  //protected $sortKey = 'Subject';
}
