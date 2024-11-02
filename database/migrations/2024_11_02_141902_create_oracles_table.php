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
        Schema::create('oracles', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_bin';

            $table->id();
            $table->string('oracle',50)->comment('Oracle account');
            $table->string('provider',32)->comment('Provider title'); //It is a string of up to 16 ASCII hex encoded characters (0x20-0x7E)
            $table->string('base',40)->comment('Base Currency');
            $table->string('quote',40)->comment('Quote Currency');
            $table->string('last_value',255)->comment('Last indexed value of this pair'); //for display only
            $table->dateTimeTz('updated_at',0)->comment('When this pair price was last updated timestamp.');

            $table->index(['oracle','provider','base','quote']);

            //$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oracles');
    }
};
