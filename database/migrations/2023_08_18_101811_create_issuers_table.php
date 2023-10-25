<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::create('issuers', function (Blueprint $table) {
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_bin';

        $table->bigIncrements('id');
        $table->string('title')->nullable()->default(null); //case sensitive in postgres
        $table->string('issuer',35); //25 to 35 characters, case sensitive in postgres
        $table->boolean('is_verified')->default(false);
        $table->boolean('is_kyc')->default(false);
        $table->string('social_twitter')->nullable()->default(null);
        //$table->timestamps();
      });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
      Schema::dropIfExists('issuers');
    }
};
