<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the success page first pushed this order's GTM `purchase` event, so
     * a refresh or revisit never fires it twice. Orders already paid before
     * this column existed are backfilled so revisiting an old confirmation
     * page doesn't report a historic sale as a new conversion.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('purchase_tracked_at')->nullable()->after('invoice_sent_at');
        });

        DB::table('orders')
            ->whereIn('status', ['active', 'completed'])
            ->update(['purchase_tracked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('purchase_tracked_at');
        });
    }
};
