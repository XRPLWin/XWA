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
      Schema::create('amms', function (Blueprint $table) {
        if(config('xwa.database_engine_userocksdb'))
          $table->engine = 'ROCKSDB';

        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_bin';

        //$table->id();
        //$table->timestamps();
        $table->string('accountid',50)->index()->comment('AMM Account ID');

        $table->string('c1',40)->comment('Amount Currency or XRP 1');
        $table->string('i1',50)->nullable()->default(null)->comment('Amount Issuer 1');
        
        $table->string('c2',40)->comment('Currency or XRP 2');
        $table->string('i2',50)->nullable()->default(null)->comment('Amount Issuer 2');

        $table->char('pairhash',180)->comment('Normalized amounts pair hash for keeping unique data');
        
        $table->string('h',64)->comment('AMM Create Transaction HASH');
        $table->timestamp('t')->comment('AMM created timestamp');
        

        // Following is proactively updated:
        $table->integer('tradingfee')->default(0)->comment('AMM current trading fee');
        $table->boolean('is_active')->default(true);

        //todo add additional columns

        $table->primary(['accountid']);
        $table->unique(['pairhash']);
      });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
      Schema::dropIfExists('amms');
    }
};
