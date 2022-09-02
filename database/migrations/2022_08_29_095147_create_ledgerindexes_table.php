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
            $table->unsignedInteger('ledger_index_first'); //first ledger index of 'day'
            $table->unsignedInteger('ledger_index_last'); //last ledger index of 'day'
            $table->date('day')->unique();
            //$table->timestamps();
        });

        Artisan::call('db:seed', [
            '--class' => 'LedgerIndexesSeeder',
            '--force' => true
        ]);
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
