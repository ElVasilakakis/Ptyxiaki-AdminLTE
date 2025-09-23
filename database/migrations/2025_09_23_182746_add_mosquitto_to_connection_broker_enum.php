<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to alter the enum to add the new value
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_broker ENUM('emqx', 'hivemq', 'thethings_stack', 'mosquitto') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove mosquitto from the enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_broker ENUM('emqx', 'hivemq', 'thethings_stack') NULL");
    }
};
