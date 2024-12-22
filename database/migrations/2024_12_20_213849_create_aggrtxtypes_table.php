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
      Schema::create('aggrtxtypes', function (Blueprint $table) {
        if(config('xwa.database_engine_userocksdb'))
          $table->engine = 'ROCKSDB';
        else
          $table->engine = 'InnoDB';
        
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_bin';

        $table->id();
        $table->string('txtype');
        $table->date('day');
        $table->unsignedBigInteger('total')->default(0); //integer storage value_uint64
        //$table->timestamps();
        $table->index(['txtype','day']);
        
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggr_tx_types');
    }
};
