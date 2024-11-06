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
        Schema::table('oracles', function (Blueprint $table) {
            $table->unsignedInteger('documentid')->default(0)->after('provider')->comment('DocumentID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oracles', function (Blueprint $table) {
            $table->dropColumn('documentid');
        });
    }
};
