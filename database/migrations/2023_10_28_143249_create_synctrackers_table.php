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
        Schema::create('synctrackers', function (Blueprint $table) {
            if(config('xwa.database_engine_userocksdb'))
                $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedInteger('first_l');
            $table->unsignedInteger('last_synced_l');
            $table->unsignedInteger('last_l');
            $table->dateTimeTz('last_lt',0)->nullable()->default(null); //Last synced ledger timestamp - used in continous syncer
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->unique(['first_l','last_l']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('synctrackers');
    }
};
