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
        if(config('xwa.database_engine') != 'sql')
            return;

        Schema::create('nftfeeds', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_bin';

            //$table->id();
            $table->uuid('id')->primary(); 
            $table->unsignedBigInteger('ctid')->comment('Transaction CTID');
            $table->dateTimeTz('t',0)->comment('Transaction Timestamp');

            $table->string('nft',64)->comment('NFTokenID or URITokenID');
            $table->string('source',50)->comment('Minter or Seller');
            $table->string('destination',50)->nullable()->default(null)->comment('Buyer');
            $table->string('broker',50)->nullable()->default(null)->comment('Broker');
            $table->unsignedBigInteger('taxon')->default(0); //collection taxon for xrpl

            //Amounts
            $table->string('a',194)->nullable()->default(null)->comment('Amount');
            $table->string('i',50)->nullable()->default(null)->comment('Issuer');
            $table->string('c',40)->nullable()->default(null)->comment('Currency');
            //Broker amount
            $table->string('ba',194)->nullable()->default(null)->comment('Broker Amount');
            $table->string('bi',50)->nullable()->default(null)->comment('Broker Issuer');
            $table->string('bc',40)->nullable()->default(null)->comment('Broker Currency');

            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nftfeeds');
    }
};
