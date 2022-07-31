<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Model;
#use Kitar\Dynamodb\Model\Model;
use BaoPham\DynamoDb\DynamoDbModel as Model;

class Account extends Model
{
  protected $table = 'transactions';
  protected $primaryKey = 'PK';
  #protected $fillable = ['id', 'account', 'title'];
  //protected $sortKey = 'Subject';
}
