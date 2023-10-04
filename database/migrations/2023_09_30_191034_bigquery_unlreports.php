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
    //\BigQuery::createDataset(config('bigquery.xwa_dataset'));

    /**
     * Create unlreports table
     */

    $fields = [
      /*[
        'name' => 'first_t',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Timestamp (Y-m-d H:i:s.uP) of first_l',
      ],
      [
        'name' => 'last_t',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Timestamp (Y-m-d H:i:s.uP) of last_l',
      ],*/
      [
        'name' => 'first_l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'First applied ledger index (flag ledger index plus 1)'
      ],
      [
        'name' => 'last_l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Last applied ledger index (flag ledger index)'
      ],
      [
        'name' => 'vlkey',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'Validator Public Key'
      ],
      [
        'name' => 'validators',
        'type' => 'STRING',
        'mode' => 'REPEATED',
        'description' => 'List of active validators'
      ],
      /*[
        'name' => 'validators',
        'type' => 'RECORD',
        'mode' => 'REPEATED',
        'fields' => [
          [
            'name' => 'pk',
            'type' => 'STRING',
            'mode' => 'REQUIRED',
            'description' => 'validators[].pk (Validator PublicKey)'
          ],
          [
            'name' => 'acc',
            'type' => 'STRING',
            'mode' => 'NULLABLE',
            'description' => 'validators[].acc (Validator Account)'
          ],
        ],
        'description' => 'List of active validators'
      ]*/
    ];

    \BigQuery::dataset(config('bigquery.xwa_dataset'))->createTable('unlreports', ['schema' => [ 'fields' => $fields ]]);
    
  }

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  public function down()
  {
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->table('unlreports')->delete();
  }
};