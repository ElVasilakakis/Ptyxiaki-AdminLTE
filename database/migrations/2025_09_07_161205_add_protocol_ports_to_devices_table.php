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
        Schema::table('devices', function (Blueprint $table) {
            $table->integer('mqtt_port')->nullable()->default(1883)->after('protocol');
            $table->integer('mqtts_port')->nullable()->default(8883)->after('mqtt_port');
            $table->integer('ws_port')->nullable()->default(8083)->after('mqtts_port');
            $table->integer('wss_port')->nullable()->default(8084)->after('ws_port');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['mqtt_port', 'mqtts_port', 'ws_port', 'wss_port']);
        });
    }
};
