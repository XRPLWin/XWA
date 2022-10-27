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
  # When creating new instance to build queries this property stores rAddress... to extract correct
  # database name via getTable()
  protected readonly string $context_address;
  //protected $hidden = ['PK', 'SK'];
  public $timestamps = false;

  /**
   * Get the table associated with the model.
   * @param ?string $char - when manually executing this function you can define character suffix for table name.
   * Detection Priority: $char, ->PK, ->context_address
   * @param ?string $char - if null it will automatically extract from ->PK, define to override
   * @return string
   */
  public function getTable(?string $char = null)
  {
    if($char === null) {
      
      $contextAddress = $this->PK;
      if(!$contextAddress)
        $contextAddress = $this->context_address;

      if(!$contextAddress)
        throw new \Exception('No Primary key (PK) set on DTransaction model before querying or context address not set');

      $char = \strtolower(\substr($contextAddress,1,1)); //rAcct... = 'a', xAcct... = 'a', ...

      if($char === '')
        throw new \Exception('Unable to extract character from context address: '.$contextAddress);
    }

    $q = config('xwa.queue_groups_reversed')[$char];
    return config('dynamodb.prefix').'transactions_'.$q;
  }

  /**
   * Creates new instance and sets context address.
   * Use this when initilazing query builder.
   * @return self instance
   */
  public static function createContextInstance(string $address)
  {
    $instance = new static;
    $instance->setContextAddress($address);
    return $instance;
  }

  /**
   * Set context_address, needed to determine table name.
   */
  public function setContextAddress(string $address)
  {
    $this->context_address = $address;
    return $this;
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
