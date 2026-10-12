<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff bookings email a payment link in the same request that creates the
     * order, but that send time was never stored. Backfill it from the order's
     * created_at for bookings that already have a recipient recorded.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('invoice_sent_at')->nullable()->after('payment_hold_expires_at');
            $table->index('invoice_sent_at');
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

                $sentTo = $data['steps']['step6']['payment_link_sent_to'] ?? null;
                $orderId = $data['steps']['step6']['order_id'] ?? null;

                if (! is_string($sentTo) || $sentTo === '' || ! is_numeric($orderId)) {
                    return;
                }

                DB::table('orders')
                    ->where('id', (int) $orderId)
                    ->whereNull('invoice_sent_at')
                    ->update(['invoice_sent_at' => DB::raw('created_at')]);
            });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['invoice_sent_at']);
            $table->dropColumn('invoice_sent_at');
        });
    }
};
