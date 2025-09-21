<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('land_id')->nullable()->constrained()->onDelete('set null');

            // Device basic info
            $table->string('device_id')->unique();
            $table->string('name');
            $table->string('device_type')->default('sensor');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('offline');

            $table->enum('connection_type', ['mqtt', 'webhook'])->default('webhook');
            $table->string('client_id')->nullable();
            $table->boolean('use_ssl')->default(false);
            $table->enum('connection_broker', ['emqx', 'hivemq', 'thethings_stack'])->nullable();
            $table->integer('port')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->boolean('auto_reconnect')->default(true);
            $table->integer('max_reconnect_attempts')->default(3);
            $table->integer('keepalive')->default(60);
            $table->integer('timeout')->default(30);

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'status']);
            $table->index('device_id');
            $table->index('connection_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
