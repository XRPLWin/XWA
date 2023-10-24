<?php

namespace App\Models;

#use Kitar\Dynamodb\Model\Model;
#use BaoPham\DynamoDb\DynamoDbModel;
#use App\Override\DynamoDb\XWDynamoDbModel;
#use App\Repository\TransactionsRepository;

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
  const TYPENAME = 'unknown';
  const CONTEXT_DEFAULT = false;

  protected $table = 'transactions';
  protected $primaryKey = 'sk';
  protected $keyType = 'string';
  public $timestamps = false;
  #const repositoryclass = TransactionsRepository::class;

  public static function getRepository(): string
  {
    if(config('xwa.database_engine') == 'bigquery')
      return \App\Repository\Bigquery\TransactionsRepository::class;
    else
      return \App\Repository\Sql\TransactionsRepository::class;
  }

  protected $fillable = [
    't',
    'l',
    'li',
    'address',
    'xwatype',
    'h',
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
    'st',
    'offers',
    'nft',
    'nftoffers',
    'hooks',
    'pc',
  ];

  protected $casts = [
    't' => 'datetime',
    'offers' => 'array',
    'nftoffers' => 'array',
    'hooks' => 'array'
  ];

  const BQCASTS = [
    't'       => 'TIMESTAMP',
    'l'       => 'INTEGER',
    'li'      => 'INTEGER',
    'address' => 'STRING',
    'xwatype' => 'INTEGER',
    'h'       => 'STRING',
    'r'       => 'STRING',
    'isin'    => 'BOOLEAN',
    'fee'     => 'NULLABLE INTEGER',
    'a'       => 'NULLABLE STRING',
    'i'       => 'NULLABLE STRING',
    'c'       => 'NULLABLE STRING',
    'a2'      => 'NULLABLE STRING',
    'i2'      => 'NULLABLE STRING',
    'c2'      => 'NULLABLE STRING',
    'dt'      => 'NULLABLE INTEGER',
    'st'      => 'NULLABLE INTEGER',
    'offers'  => 'ARRAY',
    'nft'     => 'NULLABLE STRING',
    'nftoffers'=> 'ARRAY',
    'hooks'   => 'ARRAY',
    'pc'      => 'NULLABLE STRING',
  ];

  protected function bqPrimaryKeyCondition(): string
  {
    return 'address = """'.$this->address.'""" AND t = '.$this->t;
  }

  public static function repo_fetchone(array $select, array $where, array $order): ?self
  {
    $data = self::getRepository()::fetchOne($where, $select, $order);
    if($data === null)
      return null;
    return self::hydrate([$data])->first();
  }

  /**
   * Used for API output.
   */
  public function toFinalArray(): array
  {
    $r = $this->toFinalOriginalArray();
    //cleanup fields from output:
    unset($r['address']);
    unset($r['xwatype']);
    return $r;
  }

  /**
   * Returns array of original columns.
   */
  public function toFinalOriginalArray(): array
  {
    $array = [
      'type' => $this::TYPE,
      'context' => $this::CONTEXT_DEFAULT
    ];
    return \array_merge(parent::toArray(),$array);
  }

}
