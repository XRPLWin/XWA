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
    Schema::create('hooks', function (Blueprint $table) {
      if(config('xwa.database_engine_userocksdb'))
        $table->engine = 'ROCKSDB';

      $table->charset = 'utf8mb4';
      $table->collation = 'utf8mb4_bin';

      $table->char('hook',64)->comment('Hook Hash');
      $table->unsignedInteger('l_from')->comment('LedgerIndex at which this hook was created');
      $table->unsignedInteger('l_to')->default(0)->comment('LedgerIndex at which this hook was destroyed, zero if not yet destroyed');
      $table->char('txid',64)->comment('Transaction ID this hook was created at');
      $table->char('hookon',64)->comment('HookOn value when hook was created');
      $table->json('params')->comment('Initial hook parameters as defined when hook is first created');
      $table->string('title')->comment('Identifying title of this hook');
      $table->string('descr')->comment('Identifying description of this hook');
      //$table->boolean('is_deleted')->default(false);
      $table->primary(['hook', 'l_from']);
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
