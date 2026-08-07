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
        Schema::table('instructors', function (Blueprint $table) {
            $table->unsignedTinyInteger('app_onboarding_step')->default(0)->after('payouts_enabled');
            $table->timestamp('app_onboarding_completed_at')->nullable()->after('app_onboarding_step');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn(['app_onboarding_step', 'app_onboarding_completed_at']);
        });
    }
};
