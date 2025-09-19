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
            // Remove MQTT-specific fields since we're using webhooks only
            $table->dropColumn([
                'host',
                'port',
                'websocket_port',
                'path',
                'username',
                'password',
                'use_ssl',
                'ssl_port',
                'client_id',
                'keepalive',
                'timeout',
                'certificates',
                'additional_config',
                'last_connected_at',
                'connection_error',
                'auto_reconnect',
                'max_reconnect_attempts',
            ]);

            // Update type field to reflect webhook-only usage
            $table->string('type')->default('webhook')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mqtt_brokers', function (Blueprint $table) {
            // Add back MQTT fields if needed to rollback
            $table->string('host')->nullable();
            $table->integer('port')->default(1883);
            $table->integer('websocket_port')->nullable();
            $table->string('path')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('use_ssl')->default(false);
            $table->integer('ssl_port')->nullable();
            $table->string('client_id')->nullable();
            $table->integer('keepalive')->default(60);
            $table->integer('timeout')->default(30);
            $table->json('certificates')->nullable();
            $table->json('additional_config')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->text('connection_error')->nullable();
            $table->boolean('auto_reconnect')->default(true);
            $table->integer('max_reconnect_attempts')->default(5);

            // Revert type field
            $table->string('type')->default('mosquitto')->change();
        });
    }
};
