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
            $table->string('item_type', 20)->default('slot')->after('status');
            $table->unsignedSmallInteger('travel_time_minutes')->nullable()->after('item_type');
            $table->unsignedBigInteger('parent_item_id')->nullable()->after('travel_time_minutes');

            $table->index('item_type');
            $table->foreign('parent_item_id')
                ->references('id')
                ->on('calendar_items')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_items', function (Blueprint $table) {
            $table->dropForeign(['parent_item_id']);
            $table->dropIndex(['item_type']);
            $table->dropColumn(['item_type', 'travel_time_minutes', 'parent_item_id']);
        });
    }
};
