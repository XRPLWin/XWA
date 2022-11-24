<?php

use Illuminate\Database\Migrations\Migration;
# use Illuminate\Database\Schema\Blueprint;
# use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   *
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
        'name' => 'SK',
        'type' => 'FLOAT',
        'mode' => 'REQUIRED',
        'description' => 'LedgerIndex.TxSequence'
      ],
      [
        'name' => 'PK',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'rAddress-XWTxType'
      ],
      [
        'name' => 'h',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'Transaction HASH'
      ],
      [
        'name' => 't',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Ripple epoch transaction time'
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
        'type' => 'BIGNUMERIC',
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
        'type' => 'BIGNUMERIC',
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

    \BigQuery::dataset('xwa')->createTable('transactions', ['schema' => [ 'fields' => $fields ]]);
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
