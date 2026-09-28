<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payment_hold_expires_at')->nullable()->after('stripe_checkout_session_id');
            $table->index(['status', 'payment_hold_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'payment_hold_expires_at']);
            $table->dropColumn('payment_hold_expires_at');
        });
    }
};
