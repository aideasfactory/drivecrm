<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Large imports run in stages (instructors, students, diary …), each split
     * into several jobs. `stage` is the one running now; `stage_totals` holds
     * per-stage progress and counts. A `dry_run` run only checks the file and
     * reports what an import would do.
     */
    public function up(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->boolean('dry_run')->default(false)->after('status');
            $table->string('stage', 32)->nullable()->after('dry_run');
            $table->json('stage_totals')->nullable()->after('totals');
        });
    }

    public function down(): void
    {
        Schema::table('import_runs', function (Blueprint $table) {
            $table->dropColumn(['dry_run', 'stage', 'stage_totals']);
        });
    }
};
