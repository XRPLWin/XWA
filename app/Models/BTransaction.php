<?php

namespace App\Models;

#use Kitar\Dynamodb\Model\Model;
#use BaoPham\DynamoDb\DynamoDbModel;
#use App\Override\DynamoDb\XWDynamoDbModel;
use App\Repository\TransactionsRepository;

/**
 * Transaction model.
 * PK: rAcct-<INT> SK: <INT> (Ledger index)
 * For account info  PK: rAcct     SK: 0
 * For payment       PK: rAcct-1   SK: <INT>
 * For trustset      PK: rAcct-2   SK: <INT> ...
 */
class BTransaction extends B
{
  const TYPE = 0;
  const CONTEXT_DEFAULT = false;

  protected $table = 'transactions';
  protected $primaryKey = 'sk';
  protected $keyType = 'string';
  public $timestamps = false;

  protected $fillable = [
    'SK',
    'PK',
    'h',
    't',
    'r',
    'isin',
    'fee',
    'a',
    'i',
    'c',
    'a2',
    'i2',
    'c2',
    'dt',
    'st'
  ];

  const BQCASTS = [
    'SK'    => 'FLOAT',
    'PK'    => 'STRING',
    'h'     => 'STRING',
    't'     => 'INTEGER',
    'r'     => 'STRING',
    'isin'  => 'BOOLEAN',
    'fee'   => 'INTEGER',
    'a'     => 'BIGNUMERIC',
    'i'     => 'STRING',
    'c'     => 'STRING',
    'a2'    => 'BIGNUMERIC',
    'i2'    => 'STRING',
    'c2'    => 'STRING',
    'dt'    => 'INTEGER',
    'st'    => 'INTEGER'
  ];

  /**
   * ->address
   * Extracts rAddress from PK
   * @return string rAddress
   */
  public function getAddressAttribute(): string
  {
    throw new \Exception('Not implemented');
    //TODO: explode and remove -<INT> part for Items with transaction type designation
    return $this->PK; // @phpstan-ignore-line
  }

  public function toFinalArray()
  {
    $array = [
      'type' => $this::TYPE,
      'context' => $this::CONTEXT_DEFAULT
    ];
    return \array_merge(parent::toArray(),$array);
  }

  public function save(array $options = [])
  {
    $data = parent::toArray();

    if($this->exists) {
      throw new \Exception('Update not implemented');
    }

    return TransactionsRepository::insert($data);
  }

}
