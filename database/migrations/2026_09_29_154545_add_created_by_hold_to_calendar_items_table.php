<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record whether a booking hold created the slot (true) or took over an
     * existing availability slot (false), so releasing the hold deletes slots
     * the instructor never offered instead of turning them into availability.
     * Null for items created before this column existed.
     */
    public function up(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->boolean('created_by_hold')->nullable()->after('recurrence_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropColumn('created_by_hold');
        });
    }
};
