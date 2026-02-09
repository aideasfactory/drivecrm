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
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->enum('status', ['draft', 'reserved', 'booked'])
                ->nullable()
                ->after('is_available')
                ->comment('Booking status: draft = tentative hold, reserved = weekly payment, booked = fully paid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
