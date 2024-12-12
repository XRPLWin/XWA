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
            $table->unsignedTinyInteger('type')->default(0)->after('ctid')->comment('Predefined type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nftfeeds', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
