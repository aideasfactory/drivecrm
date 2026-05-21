<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->boolean('privacy_consent')->default(false)->after('max_step_reached');
            $table->boolean('marketing_consent')->default(false)->after('privacy_consent');
            $table->timestamp('consented_at')->nullable()->after('marketing_consent');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn(['privacy_consent', 'marketing_consent', 'consented_at']);
        });
    }
};
