<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->string('mtd_itsa_status', 32)->default('unknown')->after('tax_profile_completed_at');
            $table->timestamp('mtd_itsa_status_checked_at')->nullable()->after('mtd_itsa_status');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn(['mtd_itsa_status', 'mtd_itsa_status_checked_at']);
        });
    }
};
