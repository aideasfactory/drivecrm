<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Instructor bookings made inside the 48-hour cancellation period stay
     * payable until the lesson and are never released automatically when
     * unpaid; the instructor cancels them by hand.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('booked_within_cancellation_period')->default(false)->after('payment_hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('booked_within_cancellation_period');
        });
    }
};
