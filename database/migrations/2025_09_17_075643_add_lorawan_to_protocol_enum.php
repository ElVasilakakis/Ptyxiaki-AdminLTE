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
        // Add 'lorawan' to the protocol enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN protocol ENUM('mqtt', 'mqtts', 'ws', 'wss', 'lorawan') DEFAULT 'ws'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'lorawan' from the protocol enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN protocol ENUM('mqtt', 'mqtts', 'ws', 'wss') DEFAULT 'ws'");
    }
};
