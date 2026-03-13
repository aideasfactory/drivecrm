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
            $table->string('recurrence_pattern', 20)->default('none')->after('unavailability_reason');
            $table->date('recurrence_end_date')->nullable()->after('recurrence_pattern');
            $table->uuid('recurrence_group_id')->nullable()->after('recurrence_end_date');

            $table->index('recurrence_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropIndex(['recurrence_group_id']);
            $table->dropColumn(['recurrence_pattern', 'recurrence_end_date', 'recurrence_group_id']);
        });
    }
};
