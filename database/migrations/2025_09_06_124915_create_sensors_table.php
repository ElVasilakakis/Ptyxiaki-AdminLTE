<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('device_id')->constrained()->onDelete('cascade');

            $table->string('sensor_type');
            $table->string('sensor_name');
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('min_threshold', 10, 2)->nullable();
            $table->decimal('max_threshold', 10, 2)->nullable();
            $table->json('value')->nullable();
            $table->timestamp('reading_timestamp')->nullable();
            $table->boolean('enabled')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'enabled']);
            $table->index(['device_id', 'enabled']);
            $table->index('sensor_type');
            $table->index('reading_timestamp');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensors');
    }
};
