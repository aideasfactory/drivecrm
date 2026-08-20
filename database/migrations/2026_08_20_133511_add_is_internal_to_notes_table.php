<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Internal notes are admin-only CRM notes about a pupil; they are never
     * returned to instructor-role users. Regular notes (is_internal = false)
     * remain the instructor-facing notes feature.
     */
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('note');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
