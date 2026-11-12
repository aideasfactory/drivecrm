<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Backfill: a lesson the importer created whose summary has not been
     * rewritten by a sign-off here. Sign-off always replaces the summary and
     * logs a `lesson` activity on the pupil with the lesson id.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->boolean('summary_from_import')->default(false)->after('summary');
        });

        DB::table('lessons')
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->whereExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('import_mappings')
                ->whereColumn('import_mappings.model_id', 'lessons.id')
                ->where('import_mappings.entity', 'lesson')
                ->where('import_mappings.action', 'created'))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('activity_logs')
                ->where('activity_logs.loggable_type', 'App\\Models\\Student')
                ->where('activity_logs.category', 'lesson')
                ->whereColumn('activity_logs.metadata->lesson_id', 'lessons.id'))
            ->update(['summary_from_import' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('summary_from_import');
        });
    }
};
