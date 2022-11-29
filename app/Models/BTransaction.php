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
  public string $repositoryclass = TransactionsRepository::class;

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
    't'     => 'TIMESTAMP',
    'r'     => 'STRING',
    'isin'  => 'BOOLEAN',
    'fee'   => 'NULLABLE INTEGER',
    'a'     => 'NULLABLE STRING',
    'i'     => 'NULLABLE STRING',
    'c'     => 'NULLABLE STRING',
    'a2'    => 'NULLABLE STRING',
    'i2'    => 'NULLABLE STRING',
    'c2'    => 'NULLABLE STRING',
    'dt'    => 'NULLABLE INTEGER',
    'st'    => 'NULLABLE INTEGER'
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'PK = """'.$this->PK.'""" AND SK = '.$this->SK;
  }

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

}
