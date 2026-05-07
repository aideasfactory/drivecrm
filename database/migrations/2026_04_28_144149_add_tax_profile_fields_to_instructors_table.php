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
            $table->string('business_type', 32)->nullable()->after('pdi_status');
            $table->boolean('vat_registered')->default(false)->after('business_type');
            $table->string('vrn', 9)->nullable()->unique()->after('vat_registered');
            $table->string('utr', 10)->nullable()->after('vrn');
            $table->text('nino')->nullable()->after('utr');
            $table->string('companies_house_number', 8)->nullable()->after('nino');
            $table->timestamp('tax_profile_completed_at')->nullable()->after('companies_house_number');
        });
    }

    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropUnique(['vrn']);
            $table->dropColumn([
                'business_type',
                'vat_registered',
                'vrn',
                'utr',
                'nino',
                'companies_house_number',
                'tax_profile_completed_at',
            ]);
        });
    }
};
