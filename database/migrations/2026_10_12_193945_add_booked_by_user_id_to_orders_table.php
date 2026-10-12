<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record which bookings-team user booked an order on a student's behalf.
     * Until now that only lived on the enquiry (data.staff_booking), so backfill
     * it from there using the order id saved at step 6.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('booked_by_user_id')
                ->nullable()
                ->after('invoice_sent_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('enquiries')
            ->select(['id', 'data'])
            ->whereNotNull('data->staff_booking')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $enquiry): void {
                $data = json_decode((string) $enquiry->data, true);

                if (! is_array($data)) {
                    return;
                }

                $userId = $data['staff_booking']['user_id'] ?? null;
                $orderId = $data['steps']['step6']['order_id'] ?? null;

                if (! is_numeric($userId) || ! is_numeric($orderId)) {
                    return;
                }

                if (! DB::table('users')->where('id', (int) $userId)->exists()) {
                    return;
                }

                DB::table('orders')
                    ->where('id', (int) $orderId)
                    ->whereNull('booked_by_user_id')
                    ->update(['booked_by_user_id' => (int) $userId]);
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by_user_id');
        });
    }
};
