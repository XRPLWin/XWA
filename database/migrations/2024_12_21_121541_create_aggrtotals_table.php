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
        Schema::create('aggrtotals', function (Blueprint $table) {
            if(config('xwa.database_engine_userocksdb'))
            $table->engine = 'ROCKSDB';
            else
            $table->engine = 'InnoDB';
            
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_bin';

            $table->id();
            $table->date('day')->index();
            $table->unsignedBigInteger('fee_total_drops')->default(0); //fee in drops
            $table->unsignedBigInteger('txs_count')->default(0); //total transactions (sucess and failed)
            $table->unsignedBigInteger('txs_failed_count')->default(0); //total transactions which are not tesSUCCESS
            //$table->unsignedBigInteger('txs_payment_count')->default(0); //total transactions which are not tesSUCCESS ---- ???
            $table->unsignedBigInteger('txs_rksigned_count')->default(0); //signed using regular key
            $table->unsignedBigInteger('txs_multisigned_count')->default(0);
            $table->unsignedBigInteger('txs_withmemo_count')->default(0); //total transactions with memos
            $table->unsignedBigInteger('memo_bytes')->default(0); //total memo bytes
            $table->unsignedBigInteger('ledger_count')->default(0); //total count of closed ledgers
            $table->unsignedBigInteger('min_ledger_interval_s')->default(0); //min ledger_interval in seconds
            $table->unsignedBigInteger('avg_ledger_interval_ms')->default(0); //avarage ledger_interval in miliseconds
            $table->unsignedBigInteger('max_ledger_interval_s')->default(0); //max ledger_interval in seconds
            //$table->unsignedBigInteger('account_activations_count')->default(0); //in created AccountRoot object
            //$table->unsignedBigInteger('account_deletions_count')->default(0); //in deleted AccountRoot object
            //$table->timestamps();
            //$table->primary(['day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggrtotals');
    }
};
