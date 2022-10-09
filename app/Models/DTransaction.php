<?php

namespace App\Models;

#use Illuminate\Database\Eloquent\Model;
#use Kitar\Dynamodb\Model\Model;
#use BaoPham\DynamoDb\DynamoDbModel;
use App\Override\DynamoDb\XWDynamoDbModel;

/**
 * DynamoDB Transaction model.
 * PK: rAcct-<INT> SK: <INT> (Ledger index)
 * For account info  PK: rAcct     SK: 0
 * For payment       PK: rAcct-1   SK: <INT>
 * For trustset      PK: rAcct-2   SK: <INT> ...
 */
class DTransaction extends XWDynamoDbModel
{
  const TYPE = 0;
  const CONTEXT_DEFAULT = false;

  protected $primaryKey = 'PK';
  protected $compositeKey = ['PK', 'SK'];
  //protected $hidden = ['PK', 'SK'];
  public $timestamps = false;

  /**
   * Get the table associated with the model.
   *
   * @return string
   */
  public function getTable()
  {
    return config('dynamodb.prefix').'transactions';
  }

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
    return $this->PK; // @phpstan-ignore-line
  }

  public function toArray()
  {
    $array = [
      'type' => $this::TYPE,
      'context' => $this::CONTEXT_DEFAULT
    ];
    $array = \array_merge(parent::toArray(),$array);
    return $array;
  }

}
