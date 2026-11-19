<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record on a retired instructor profile that it was merged into another
     * (MergeInstructorAccountsAction): into whom, by which owner, when, and the
     * login email it had before the merge rewrote it.
     */
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->foreignId('merged_into_instructor_id')->nullable()->after('status')->constrained('instructors')->nullOnDelete();
            $table->foreignId('merged_by_user_id')->nullable()->after('merged_into_instructor_id')->constrained('users')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_by_user_id');
            $table->string('merged_original_email')->nullable()->after('merged_at');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_instructor_id');
            $table->dropConstrainedForeignId('merged_by_user_id');
            $table->dropColumn(['merged_at', 'merged_original_email']);
        });
    }
};
