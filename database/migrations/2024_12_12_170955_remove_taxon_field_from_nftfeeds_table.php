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
        Schema::table('nftfeeds', function (Blueprint $table) {
            $table->dropColumn('taxon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nftfeeds', function (Blueprint $table) {
            $table->unsignedBigInteger('taxon')->default(0); //collection taxon for xrpl
        });
    }
};
