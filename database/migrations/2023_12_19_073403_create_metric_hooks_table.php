<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily metric for hooks - per hook hash, per day.
     */
    public function up(): void
    {
        Schema::create('metric_hooks', function (Blueprint $table) {
            $table->id();
            $table->char('hook',64)->comment('Hook Hash');
            $table->date('day');
            $table->unsignedInteger('num_active_installs')->default(0)->comment('Sum of accounts which have this hook installed on this day');
            $table->unsignedInteger('num_installs')->default(0)->comment('Num installed to accounts');
            $table->unsignedInteger('num_uninstalls')->default(0)->comment('Num uninstalled from accounts');
            $table->unsignedInteger('num_exec')->default(0)->comment('Num executions'); //main general metric
            $table->unsignedInteger('num_exec_accepts')->default(0)->comment('Num executions that returned accept code');
            $table->unsignedInteger('num_exec_rollbacks')->default(0)->comment('Num executions that returned rollback code');
            $table->unsignedInteger('num_exec_other')->default(0)->comment('Num executions that returned other code');
            #$table->timestamps();
            $table->unique(['hook','day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metric_hooks');
    }
};
