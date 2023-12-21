<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    //TODO BIGQUERY MIGRATION
    
    Schema::create('hooks', function (Blueprint $table) {
      if(config('xwa.database_engine_userocksdb'))
        $table->engine = 'ROCKSDB';

      $table->charset = 'utf8mb4';
      $table->collation = 'utf8mb4_bin';

      $table->char('hook',64)->comment('Hook Hash');
      $table->unsignedBigInteger('ctid_from')->comment('CTID at which this hook was created');
      $table->unsignedBigInteger('ctid_to')->default(0)->comment('CTID at which this hook was destroyed, zero if not yet destroyed');
      //$table->unsignedInteger('l_from')->comment('LedgerIndex at which this hook was created');
      //$table->unsignedSmallInteger('li_from')->comment('TransactionIndex at which this hook was created');
      //$table->unsignedInteger('l_to')->default(0)->comment('LedgerIndex at which this hook was destroyed, zero if not yet destroyed');
      //$table->unsignedSmallInteger('li_to')->comment('TransactionIndex at which this hook was created');
      $table->string('owner',50)->comment('Owner rAddress - account who first created a hookdef');
      //$table->char('txid',64)->comment('Transaction ID this hook was created at');
      //$table->char('txid_last',64)->nullable()->default(null)->comment('Transaction ID this hook was destroyed at, null if not destroyed yet');
      $table->char('hookon',64)->comment('HookOn value when hook was created');
      $table->json('params')->comment('Initial hook parameters as defined when hook is first created');
      $table->char('namespace',64)->comment('Initial hook namespace, null namespace is filled with zeros');
      //$table->string('title')->comment('Identifying title of this hook');
      //$table->string('descr')->comment('Identifying description of this hook');
      $table->unsignedInteger('stat_installs')->default(0)->comment('Number of installations');
      $table->unsignedInteger('stat_uninstalls')->default(0)->comment('Number of uninstallations');
      $table->unsignedInteger('stat_exec')->default(0)->comment('Number of executions');
      $table->unsignedInteger('stat_exec_rollbacks')->default(0)->comment('Number of rollbacks');
      $table->unsignedInteger('stat_exec_accepts')->default(0)->comment('Number of accepts');
      $table->unsignedInteger('stat_exec_other')->default(0)->comment('Number of fails including unset');
      //$table->unsignedInteger('stat_fee_min')->default(0)->comment('Minimal fee detected');
      //$table->unsignedInteger('stat_fee_max')->default(0)->comment('Maximal fee detected');

      $table->primary(['hook', 'ctid_from']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('hooks');
  }
};
