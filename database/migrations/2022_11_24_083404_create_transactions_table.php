<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonPeriod;

return new class extends Migration
{
  public function up()
  {
    if(config('xwa.database_engine') == 'bigquery')
      return $this->up_bigquery();
    else
      return $this->up_sql();
  }

  protected function up_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;

    /**
     * Create transactions table
     * @see https://cloud.google.com/bigquery/docs/reference/standard-sql/data-types
     */
    
    $fields = [
      [
        'name' => 't',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Timestamp (Y-m-d H:i:s.uP)',
      ],
      [
        'name' => 'l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'LedgerIndex' //ledger index of this transaction
      ],
      [
        'name' => 'li',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'TransactionIndex' //position inside ledgerindex
      ],
      /*[
        'name' => 'SK',
        'type' => 'FLOAT',
        'mode' => 'REQUIRED',
        'description' => 'LedgerIndex.TxSequence'
      ],*/
      [
        'name' => 'address',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'rAddress'
      ],
      [
        'name' => 'xwatype',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'XWA Transaction Type'
      ],
      [
        'name' => 'h',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'Transaction HASH'
      ],
      
      [
        'name' => 'r',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'Counterparty'
      ],
      [
        'name' => 'isin',
        'type' => 'BOOLEAN',
        'mode' => 'REQUIRED',
        'description' => 'Direction (in or out)'
      ],
      [
        'name' => 'fee',
        'type' => 'INTEGER',
        'mode' => 'NULLABLE',
        'description' => 'Fee in drops'
      ],
      [
        'name' => 'a',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Amount'
      ],
      [
        'name' => 'i',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Issuer'
      ],
      [
        'name' => 'c',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Currency'
      ],
      [
        'name' => 'a2',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Amount (secondary)'
      ],
      [
        'name' => 'i2',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Issuer (secondary)'
      ],
      [
        'name' => 'c2',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Currency (secondary)'
      ],
      [
        'name' => 'offers',
        'type' => 'STRING',
        'mode' => 'REPEATED',
        'description' => 'List of offers that are affected in specific transaction in format: "rAccount:sequence"'
      ],
      [
        'name' => 'nft',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'NFTokenID'
      ],
      [
        'name' => 'nftoffers',
        'type' => 'STRING',
        'mode' => 'REPEATED',
        'description' => 'List of NFTOfferIDs that are affected in specific transaction'
      ],
     /* [
        'name' => 'nftoffers',
        'type' => 'RECORD',
        'mode' => 'REPEATED',
        'fields' => [
          'name' => 'id',
          'type' => 'STRING',
          'mode' => 'REQUIRED',
          'description' => 'nftoffers[].id'
        ],
        'description' => 'List of NFTOfferIDs that are affected in specific transaction'
      ],*/
      [
        'name' => 'pc',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Payment Channel'
      ],
      [
        'name' => 'hooks',
        'type' => 'STRING',
        'mode' => 'REPEATED',
        'description' => 'List of executed hook hashes'
      ],
      [
        'name' => 'dt',
        'type' => 'INTEGER',
        'mode' => 'NULLABLE',
        'description' => 'Destination Tag'
      ],
      [
        'name' => 'st',
        'type' => 'INTEGER',
        'mode' => 'NULLABLE',
        'description' => 'Source Tag'
      ],
      /*[
          'name' => 'auth_token',
          'type' => 'string'
      ],
      [
          'name' => 'remember_token',
          'type' => 'string'
      ],
      [
          'name' => 'created_at',
          'type' => 'datetime'
      ],
      [
          'name' => 'updated_at',
          'type' => 'datetime'
      ],*/
    ];

    \BigQuery::dataset(config('bigquery.xwa_dataset'))->createTable('transactions', [
      'schema' => [ 'fields' => $fields ],
      'timePartitioning' => [
        'type' => 'MONTH',
        'field' => 't'
      ]
    ]);
  }

  /**
   * Create 10 years of monthly tables (future)
   * Horizontal sharding by month.
   */
  private function period(): CarbonPeriod
  {
    $startdate = ripple_epoch_to_carbon(config('xrpl.'.config('xrpl.net').'.genesis_ledger_close_time'));
    $enddate = now()->addYears(2);
    return CarbonPeriod::create($startdate, '1 month', $enddate);
  }

  /**
   * Create transactions table on SQL database.
   */
  protected function up_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;
    $period = $this->period();
    foreach($period as $m) {
      Schema::create(transactions_db_name($m->format('Ym')), function (Blueprint $table) {
        if(config('xwa.database_engine_userocksdb'))
          $table->engine = 'ROCKSDB';
        //$table->bigIncrements('id');
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_bin';
       
        $table->string('address',50)->index()->comment('rAddress');
        $table->unsignedInteger('l')->comment('LedgerIndex');
        $table->unsignedSmallInteger('li')->comment('TransactionIndex');
        $table->dateTimeTz('t',0)->comment('Transaction Timestamp');
        
        $table->unsignedSmallInteger('xwatype')->comment('XWA Transaction Type');
        $table->string('h',64)->comment('Transaction HASH');
        $table->string('r',50)->comment('Counterparty');
        $table->boolean('isin')->default(true)->comment('Direction (in or out)');
        $table->unsignedInteger('fee')->nullable()->default(null)->comment('Fee in drops');
        //-999,999,999,999,999,900,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000
        // 999,999,999,999,999,900,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000,000
        // length: 96
        //$table->decimal('a',96,0)->comment('Amount');
        $table->string('a',194)->nullable()->default(null)->comment('Amount');
        $table->string('i',50)->nullable()->default(null)->comment('Issuer');
        $table->string('c',40)->nullable()->default(null)->comment('Currency');
        $table->string('a2',194)->nullable()->default(null)->comment('Amount (secondary)');
        $table->string('i2',50)->nullable()->default(null)->comment('Issuer (secondary)');
        $table->string('c2',40)->nullable()->default(null)->comment('Currency (secondary)');
        $table->json('offers')->comment('List of offers that are affected in specific transaction in format: rAccount:sequence');
        $table->string('nft',64)->nullable()->default(null)->comment('NFTokenID');
        $table->json('nftoffers')->comment('List of NFTOfferIDs that are affected in specific transaction');
        $table->string('pc',64)->nullable()->default(null)->comment('Payment channel');
        $table->json('hooks')->comment('List of executed hook hashes');
        $table->unsignedBigInteger('dt')->nullable()->default(null)->comment('Destination Tag');
        $table->unsignedBigInteger('st')->nullable()->default(null)->comment('Source Tag');
  
        $table->primary(['address', 'l', 'li', 'xwatype']);
      });
    }
    
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    if(config('xwa.database_engine') == 'bigquery')
      return $this->down_bigquery();
    else
      return $this->down_sql();
  }

  protected function down_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;
      
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->table('transactions')->delete();
  }

  protected function down_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;
    $period = $this->period();
    foreach($period as $m) {
      Schema::dropIfExists(transactions_db_name($m->format('Ym')));
    }
  }
};
