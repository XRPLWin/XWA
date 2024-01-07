<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('trackers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->char('subject',10)->unique(); //10 chars string
            $table->unsignedBigInteger('value_uint64')->default(0); //integer storage
            $table->integer('value_int')->default(0); //integer storage
            #$table->string('value_string',100); //100 char storage
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trackers');
    }
};
