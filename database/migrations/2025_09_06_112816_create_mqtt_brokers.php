<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('mqtt_brokers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['emqx', 'mosquitto', 'lorawan'])->default('mosquitto');
            $table->string('host');
            $table->integer('port')->default(1883);
            $table->integer('websocket_port')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('use_ssl')->default(false);
            $table->integer('ssl_port')->nullable();
            $table->string('client_id')->nullable();
            $table->integer('keepalive')->default(60);
            $table->integer('timeout')->default(30);
            $table->json('certificates')->nullable(); // SSL certificates paths
            $table->json('additional_config')->nullable(); // Extra broker-specific settings
            $table->enum('status', ['active', 'inactive', 'error'])->default('inactive');
            $table->timestamp('last_connected_at')->nullable();
            $table->text('connection_error')->nullable();
            $table->boolean('auto_reconnect')->default(true);
            $table->integer('max_reconnect_attempts')->default(5);
            $table->text('description')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'status']);
            $table->index(['user_id', 'status']);
            $table->unique(['host', 'port'], 'unique_broker_endpoint');
        });
    }

    public function down()
    {
        Schema::dropIfExists('mqtt_brokers');
    }
};
