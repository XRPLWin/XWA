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
        Schema::table('amms', function (Blueprint $table) {
            //$table->double('high24')->default(0)->after('lpa')->comment('24h amm only high');
            //$table->double('low24')->default(0)->after('lpa')->comment('24h amm only low');
            //$table->double('volume24')->default(0)->after('lpa')->comment('24h amm only volume');
            $table->double('tvl')->default(0)->after('lpa')->comment('Total Value Locked');
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amms', function (Blueprint $table) {
            $table->dropColumn('tvl');
            //$table->dropColumn('volume24');
            //$table->dropColumn('low24');
            //$table->dropColumn('high24');
        });
    }
};
