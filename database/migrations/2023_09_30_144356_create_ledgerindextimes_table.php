<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledgerindextimes', function (Blueprint $table) {
            if(config('xwa.database_engine_userocksdb'))
                $table->engine = 'ROCKSDB';
            $table->id();
            $table->date('day_start');
            $table->bigInteger('ledger_index');
            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledgerindextimes');
    }
};
