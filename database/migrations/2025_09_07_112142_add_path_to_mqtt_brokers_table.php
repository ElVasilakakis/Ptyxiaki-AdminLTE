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
        Schema::table('mqtt_brokers', function (Blueprint $table) {
            $table->string('path')->default('/mqtt')->after('websocket_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mqtt_brokers', function (Blueprint $table) {
            $table->dropColumn('path');
        });
    }
};
