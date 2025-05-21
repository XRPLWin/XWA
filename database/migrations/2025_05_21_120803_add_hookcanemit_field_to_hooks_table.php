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
        Schema::table('hooks', function (Blueprint $table) {
            $table->char('hookcanemit',64)->nullable()->default(null)->after('hookon')->comment('HookCanEmit value when hook was created (optional)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hooks', function (Blueprint $table) {
            $table->dropColumn('hookcanemit');
        });
    }
};
