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

    $fields = [
      [
        'name' => 'validator',
        'type' => 'STRING',
        'mode' => 'REQUIRED',
        'description' => 'Validator Public Key'
      ],
      [
        'name' => 'account',
        'type' => 'STRING',
        'mode' => 'NULLABLE',
        'description' => 'Account (r-address) of this validator'
      ],
      [
        'name' => 'first_l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'First appeared ledger index (flag ledger index plus 1)'
      ],
      [
        'name' => 'last_l',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Last appeared ledger index (flag ledger)'
      ],
      [
        'name' => 'current_successive_fl_count',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Current flag ledger for sucessive counting'
      ],
      [
        'name' => 'max_successive_fl_count',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Number of flag ledgers this validator ran without interruptions'
      ],
      [
        'name' => 'active_fl_count',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Number of flag ledgers this validator appeared in'
      ],
      /*[
        'name' => 'total_claimed',
        'type' => 'INTEGER',
        'mode' => 'REQUIRED',
        'description' => 'Claimed drops'
      ],*/
    ];
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->createTable('unlvalidators', ['schema' => [ 'fields' => $fields ]]);
  }

  /**
   * Create accounts table on SQL database.
   */
  protected function up_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::create('unlvalidators', function (Blueprint $table) {
      //$table->bigIncrements('id');
      $table->string('validator',66)->index()->comment('Validator Public Key'); //eg EDA4A1278B9FDCABFAE094956DB1D7A0FCB9E99E40FB02C8ED26E6B2C4B83DB932
      $table->string('account',50)->index()->comment('Account (r-address) of this validator');
      $table->unsignedInteger('first_l')->comment('First appeared ledger index (flag ledger index plus 1)');
      $table->unsignedInteger('last_l')->comment('Last appeared ledger index (flag ledger)');
      $table->unsignedInteger('current_successive_fl_count')->comment('Current flag ledger for sucessive counting');
      $table->unsignedInteger('max_successive_fl_count')->comment('Number of flag ledgers this validator ran without interruptions');
      $table->unsignedInteger('active_fl_count')->comment('Number of flag ledgers this validator appeared in');
      
      //Add primary key:
      $table->primary('validator');
    
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
      
    \BigQuery::dataset(config('bigquery.xwa_dataset'))->table('unlvalidators')->delete();
  }

  protected function down_sql()
  {
    if(config('xwa.database_engine') != 'sql')
      return;

    Schema::dropIfExists('unlvalidators');
    
  }
};
