<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record the structured reason a lesson was cancelled
     * (App\Enums\LessonCancellationReason). `cancellation_reason` stays as the
     * free-text details. Existing cancelled lessons stay null.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('cancellation_reason_code', 50)->nullable()->after('cancellation_reason');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason_code');
        });
    }
};
