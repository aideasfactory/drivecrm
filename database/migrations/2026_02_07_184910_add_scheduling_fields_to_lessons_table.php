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
            // Add scheduling fields (nullable for existing records)
            $table->date('date')->nullable()->after('amount_pence');
            $table->time('start_time')->nullable()->after('date');
            $table->time('end_time')->nullable()->after('start_time');

            // Add foreign key to calendar_items
            $table->foreignId('calendar_item_id')
                ->nullable()
                ->after('end_time')
                ->constrained('calendar_items')
                ->onDelete('set null');

            // Add index for performance
            $table->index('calendar_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Drop foreign key and index first
            $table->dropForeign(['calendar_item_id']);
            $table->dropIndex(['calendar_item_id']);

            // Drop columns
            $table->dropColumn([
                'calendar_item_id',
                'end_time',
                'start_time',
                'date',
            ]);
        });
    }
};
