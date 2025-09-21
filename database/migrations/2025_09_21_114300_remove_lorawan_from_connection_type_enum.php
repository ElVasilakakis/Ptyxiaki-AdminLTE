<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove 'lorawan' from the connection_type enum, keeping only 'mqtt' and 'webhook'
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_type ENUM('mqtt', 'webhook') DEFAULT 'webhook'");
    }

    public function down(): void
    {
        // Add 'lorawan' back to the connection_type enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_type ENUM('mqtt', 'webhook', 'lorawan') DEFAULT 'webhook'");
    }
};
