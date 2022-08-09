<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Model;
#use Kitar\Dynamodb\Model\Model;
use BaoPham\DynamoDb\DynamoDbModel as Model;

/**
 * DynamoDB Transaction model.
 * PK: rAcct-<INT> SK: <INT> (Ledger index)
 * For account info  PK: rAcct     SK: 0
 * For payment       PK: rAcct-1   SK: <INT>
 * For trustset      PK: rAcct-2   SK: <INT> ...
 */
class DTransaction extends Model
{
  //const .. object types todo
  
  protected $table = 'transactions';
  protected $primaryKey = 'PK';
  protected $compositeKey = ['PK', 'SK'];
  public $timestamps = false;

  const TX_PAYMENT = 1;

  //protected $fillable = ['id', 'account', 'title'];
  //protected $sortKey = 'Subject';

  /**
   * ->address
   * Extracts rAddress from PK
   * @return string rAddress
   */
  public function getAddressAttribute(): string
  {
    //TODO: explode and remove -<INT> part for Items with transaction type designation
    return $this->PK;
  }

  public function type()
  {
    //TODO return transaction type which is contained in PK
  }
}
