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
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('booking_fee_pence')->default(0)->after('package_lessons_count');
            $table->unsignedInteger('digital_fee_pence')->default(0)->after('booking_fee_pence');
            $table->unsignedInteger('total_price_pence')->nullable()->after('digital_fee_pence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['booking_fee_pence', 'digital_fee_pence', 'total_price_pence']);
        });
    }
};
