<?php

use Illuminate\Database\Migrations\Migration;
# use Illuminate\Database\Schema\Blueprint;
# use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Note: BigQuery tables are not instantly available, it takes about 10 seconds to be available.
   * @see https://github.com/prologuetech/laravel-big/blob/master/src/Big.php
   * @return void
   */
  public function up()
  {
    \BigQuery::createDataset('xwa');

    /**
     * Create accounts table
     */

    $fields = [
      [
          'name' => 'address',
          'type' => 'STRING',
          'mode' => 'REQUIRED',
          'description' => 'rAddress'
      ],
      [
        'name' => 'l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Last synced ledger index'
      ],
      [
        'name' => 'lt',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Last synced ledger timestamp (Y-m-d H:i:s.uP)',
      ],
      [
        'name' => 'activatedBy',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'rAddress which activated this account'
      ],
      [
        'name' => 'isdeleted',
        'type' => 'BOOLEAN',
        'mode' => 'REQUIRED',
        'description' => 'Is this account deleted (yes or no)'
      ],
    ];

    \BigQuery::dataset('xwa')->createTable('accounts', ['schema' => [ 'fields' => $fields ]]);
    unset($fields);


    /**
     * Create transactions table
     */
    
    $fields = [
      [
        'name' => 't',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Timestamp (Y-m-d H:i:s.uP)',
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
        'name' => 'nft',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'NFTokenID'
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

    \BigQuery::dataset('xwa')->createTable('transactions', [
      'schema' => [ 'fields' => $fields ],
      'timePartitioning' => [
        'type' => 'MONTH',
        'field' => 't'
      ]
    ]);
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    \BigQuery::dataset('xwa')->delete(['deleteContents' => true]);
  }
};
