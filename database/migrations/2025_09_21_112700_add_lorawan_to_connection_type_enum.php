<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add 'lorawan' to the connection_type enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_type ENUM('mqtt', 'webhook', 'lorawan') DEFAULT 'webhook'");
    }

    public function down(): void
    {
        // Remove 'lorawan' from the connection_type enum
        DB::statement("ALTER TABLE devices MODIFY COLUMN connection_type ENUM('mqtt', 'webhook') DEFAULT 'webhook'");
    }
};
