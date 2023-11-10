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

  protected function up_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;

    \BigQuery::createDataset(config('bigquery.xwa_dataset'));

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
        'name' => 'l',  //not used in continous syncer
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Last synced ledger index'
      ],
      [
        'name' => 'li',  //not used in continous syncer
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'TransactionIndex' //position inside ledgerindex
      ],
      [
        'name' => 'lt',  //not used in continous syncer
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

    \BigQuery::dataset(config('bigquery.xwa_dataset'))->createTable('accounts', ['schema' => [ 'fields' => $fields ]]);
    unset($fields);
  }

  /**
   * Create accounts table on SQL database.
   */
  protected function up_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::create('accounts', function (Blueprint $table) {
      //$table->bigIncrements('id');
      $table->charset = 'utf8mb4';
      $table->collation = 'utf8mb4_bin';
      
      $table->string('address',50);
      $table->unsignedInteger('l'); //not used in continous syncer
      $table->unsignedSmallInteger('li'); //not used in continous syncer
      $table->dateTimeTz('lt',0); //Last synced ledger timestamp - not used in continous syncer
      $table->string('activatedBy',50)->nullable()->default(null)->comment('rAddress which activated this account'); //first one
      $table->boolean('isdeleted')->default(false);

      //Add primary key:
      $table->primary('address');
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

  protected function down_bigquery()
  {
    if(config('xwa.database_engine') != 'bigquery')
      return;
      
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->delete(['deleteContents' => true]);
  }

  protected function down_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::dropIfExists('accounts');
  }
};
