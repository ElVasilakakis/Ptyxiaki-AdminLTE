<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->unique();
            $table->string('name');
            $table->string('device_type')->default('sensor');
            $table->foreignId('mqtt_broker_id')->constrained('mqtt_brokers')->onDelete('cascade');
            $table->foreignId('land_id')->nullable()->constrained('lands')->onDelete('set null');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Location as GeoJSON
            $table->json('location'); // GeoJSON Point            
            
            // Device status and connection
            $table->enum('status', ['online', 'offline', 'error', 'maintenance'])->default('offline');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            
            // MQTT Topics
            $table->json('topics');
            
            // Device info
            $table->json('configuration')->nullable();
            
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'is_active']);
            $table->index(['mqtt_broker_id', 'status']);
            $table->index(['user_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('devices');
    }
};
