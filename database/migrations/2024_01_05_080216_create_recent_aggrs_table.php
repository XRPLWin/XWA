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
        Schema::create('recent_aggrs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_bin';

            //$table->id();
            $table->char('subject',10);
            $table->string('identifier',254);
            $table->date('day');
            $table->unsignedBigInteger('value_uint64')->default(0); //integer storage
            $table->string('context',254);
            //$table->timestamps();
            $table->primary(['subject','identifier','day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recent_aggrs');
    }
};
