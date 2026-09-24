<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE calendar_items MODIFY COLUMN status ENUM('draft', 'reserved', 'booked', 'completed') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE calendar_items MODIFY COLUMN status ENUM('draft', 'reserved', 'booked') NULL");
    }
};
