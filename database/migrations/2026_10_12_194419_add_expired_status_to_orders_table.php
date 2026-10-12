<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unpaid bookings released when their payment hold runs out were marked
     * `cancelled`, the same as bookings someone actually cancelled. Give them
     * their own `expired` status.
     *
     * Backfill: a release deletes the order's lessons, whereas a cancellation
     * keeps them (status `cancelled`). Only held orders are ever released, so a
     * cancelled order with a hold and no lessons left was released.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled', 'expired'])
                ->default('pending')
                ->change();
        });

        DB::table('orders')
            ->where('status', 'cancelled')
            ->whereNotNull('payment_hold_expires_at')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('lessons')
                    ->whereColumn('lessons.order_id', 'orders.id');
            })
            ->update(['status' => 'expired']);
    }

    public function down(): void
    {
        DB::table('orders')
            ->where('status', 'expired')
            ->update(['status' => 'cancelled']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }
};
