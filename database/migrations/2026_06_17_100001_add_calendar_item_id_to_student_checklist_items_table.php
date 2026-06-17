<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_checklist_items', function (Blueprint $table) {
            $table->foreignId('calendar_item_id')
                ->nullable()
                ->after('notes')
                ->constrained('calendar_items')
                ->nullOnDelete();

            $table->index('calendar_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('student_checklist_items', function (Blueprint $table) {
            $table->dropForeign(['calendar_item_id']);
            $table->dropIndex(['calendar_item_id']);
            $table->dropColumn('calendar_item_id');
        });
    }
};
