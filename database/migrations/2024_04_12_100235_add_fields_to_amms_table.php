<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('amms', function (Blueprint $table) {
            $table->string('c1_display',40)->after('c1')->comment('Amount Currency decoded or XRP 1');
            $table->string('c2_display',40)->after('c2')->comment('Amount Currency decoded or XRP 2');
        });

        //update existing data
        $amms = DB::table('amms')->get();
        foreach($amms as $amm) {
            DB::table('amms')
                ->where('accountid',$amm->accountid)
                ->update([
                    'c1_display' => xrp_currency_to_symbol($amm->c1),
                    'c2_display' => xrp_currency_to_symbol($amm->c2),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amms', function (Blueprint $table) {
            $table->dropColumn('c1_display');
            $table->dropColumn('c2_display');
        });
    }
};
