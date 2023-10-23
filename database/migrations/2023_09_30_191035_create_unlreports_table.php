<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

  public function up()
  {
    if(config('xwa.database_engine') == 'bigquery')
      return $this->up_bigquery();
    else
      return $this->up_sql();
  }


  /**
   * Run the migrations.
   * Note: BigQuery tables are not instantly available, it takes about 10 seconds to be available.
   * @see https://github.com/prologuetech/laravel-big/blob/master/src/Big.php
   * @return void
   */
  protected function up_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;

    //\BigQuery::createDataset(config('bigquery.xwa_dataset'));

    /**
     * Create unlreports table
     */

    $fields = [
      /*[
        'name' => 'first_t',
        'type' => 'TIMESTAMP',
        'mode' => 'REQUIRED',
        'description' => 'Timestamp of first ledger_index occurrence (Y-m-d H:i:s.uP)',
      ],*/
      /*[
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
   * Create accounts table on SQL database.
   */
  protected function up_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::create('unlreports', function (Blueprint $table) {
      $table->unsignedInteger('first_l')->comment('First applied ledger index (flag ledger index plus 1)');
      //$table->unsignedInteger('first_l')->comment('First applied ledger index (flag ledger index plus 1)');
      $table->unsignedInteger('last_l')->comment('Last applied ledger index (flag ledger index)');
      $table->string('vlkey',66)->nullable()->default(null)->comment('Validator Public Key'); //eg ED45D1840EE724BE327ABE9146503D5848EFD5F38B6D5FEDE71E80ACCE5E6E738B
      $table->json('validators')->comment('List of active validators');

      //Add primary key:
      $table->primary('first_l');
    });
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

  /**
   * Reverse the migrations.
   *
   * @return void
   */
  protected function down_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;
      
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->table('unlreports')->delete();
  }

  protected function down_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::dropIfExists('unlreports');
    
  }
};
