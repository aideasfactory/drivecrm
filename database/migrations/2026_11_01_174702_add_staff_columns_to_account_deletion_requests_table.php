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
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('previous_instructor_status', 20)->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_deletion_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropColumn('previous_instructor_status');
        });
    }
};
