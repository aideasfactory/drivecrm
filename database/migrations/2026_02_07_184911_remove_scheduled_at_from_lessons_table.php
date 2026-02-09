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
        Schema::table('lessons', function (Blueprint $table) {
            // Remove old scheduled_at field (replaced by date + start_time + end_time)
            $table->dropColumn('scheduled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Restore scheduled_at field
            $table->dateTime('scheduled_at')->nullable()->after('lesson_number');
        });
    }
};
