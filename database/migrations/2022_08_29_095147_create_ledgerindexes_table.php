<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ledgerindexes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('ledger_index_first'); //first ledger index of 'day' - for Postgres this is int8 with 64 length
            $table->bigInteger('ledger_index_last');  //last ledger index of 'day' - can be -1 for current day - for Postgres this is int8 with 64 length
            $table->date('day')->unique();
            //$table->timestamps();
        });

        /*Artisan::call('db:seed', [
            '--class' => 'LedgerIndexesSeeder',
            '--force' => true
        ]);*/
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ledgerindexes');
    }
};
