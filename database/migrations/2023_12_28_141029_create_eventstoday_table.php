<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  /**
   * Run the migrations.
   * Store various short-term stats.
   */
  public function up(): void
  {
    Schema::create('eventstoday', function (Blueprint $table) {
      if(config('xwa.database_engine_userocksdb'))
        $table->engine = 'InnoDB';
      
      $table->id();
      $table->char('subject',10)->unique(); //10 chars string
      $table->unsignedBigInteger('value_uint64')->default(0); //integer storage
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('eventstoday');
  }
};
