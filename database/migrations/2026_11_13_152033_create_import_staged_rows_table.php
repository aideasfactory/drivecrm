<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Temporary holding area for an uploaded import zip: every CSV row is read
     * in once, then the import jobs work through it a few instructors at a
     * time. Emptied when the run finishes.
     */
    public function up(): void
    {
        Schema::create('import_staged_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('file', 32);
            $table->string('instructor_ref', 64)->nullable();
            $table->string('source_ref', 191)->nullable();
            $table->unsignedInteger('line');
            $table->json('row');

            $table->index(['import_run_id', 'file', 'instructor_ref']);
            $table->index(['import_run_id', 'file', 'source_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_staged_rows');
    }
};
