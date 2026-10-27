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
            $table->timestamp('banked_at')->nullable()->after('provisional_at')->index();
            $table->string('banked_reason', 1100)->nullable()->after('banked_at');
            $table->foreignId('banked_by_user_id')->nullable()->after('banked_reason')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('banked_by_user_id');
            $table->dropIndex(['banked_at']);
            $table->dropColumn(['banked_at', 'banked_reason']);
        });
    }
};
