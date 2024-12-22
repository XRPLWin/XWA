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
        Schema::create('aggrobjects', function (Blueprint $table) {
            if(config('xwa.database_engine_userocksdb'))
                $table->engine = 'ROCKSDB';
            else
                $table->engine = 'InnoDB';
            
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_bin';

            $table->id();
            $table->string('objtype');
            $table->date('day');
            $table->unsignedBigInteger('created_count')->default(0); //integer storage
            $table->unsignedBigInteger('deleted_count')->default(0); //integer storage
            //$table->timestamps();
            $table->index(['objtype','day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aggrobjects');
    }
};
