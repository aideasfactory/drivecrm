<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Typed discount codes: a code learners enter at onboarding step 6, and
     * fixed £ amounts as well as percentages. Existing link-only codes keep
     * a null code and stay percentage codes.
     */
    public function up(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->unique()->after('id');
            $table->string('type', 20)->default('percentage')->after('label');
            $table->unsignedInteger('amount_pence')->nullable()->after('percentage');
            $table->unsignedTinyInteger('percentage')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('discount_codes', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'type', 'amount_pence']);
            $table->unsignedTinyInteger('percentage')->nullable(false)->change();
        });
    }
};
