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

    /**
     * This table stores latest hook transaction (max 1000 per hook)
     * Older transactions are dropped.
     */
    Schema::create('hook_transactions', function (Blueprint $table) {
      if(config('xwa.database_engine_userocksdb'))
        $table->engine = 'ROCKSDB';
      
      $table->charset = 'utf8mb4';
      $table->collation = 'utf8mb4_bin';

      //$table->id();
      $table->uuid('id')->primary(); 
      $table->char('hook',64)->comment('Hook Hash (index)');
      $table->char('h',64)->comment('Transaction hash');
      $table->unsignedInteger('l')->comment('LedgerIndex at which transaction was executed');
      $table->dateTimeTz('t',0)->comment('Transaction Timestamp');
      $table->string('r',50)->comment('Transaction Initiator rAddress');
      $table->string('txtype',50)->comment('Transaction type name');
      $table->string('tec',100)->comment('Transaction tec Code');
      $table->unsignedTinyInteger('hookaction')->comment('Hook action code'); //install,uninstall etc..
      $table->unsignedTinyInteger('hookresult')->default(0)->comment('HookResult code if hook was executed');
      #$table->timestamps();
      //$table->primary(['hook', 'h']);
      $table->index(['hook']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('hook_transactions');
  }
};
