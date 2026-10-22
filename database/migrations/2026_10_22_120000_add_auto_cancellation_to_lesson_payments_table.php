<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backstop for lessons nobody cancelled: `lessons:auto-cancel-overdue-invoices`
     * stops a weekly lesson payment that is still unpaid a set number of days
     * after it fell due. Its Stripe invoice is voided and it is never invoiced
     * or chased again. The row stays `due` (it was not paid or refunded); these
     * columns record when and why it was stopped. Must run before the code that
     * reads it is deployed.
     */
    public function up(): void
    {
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->timestamp('auto_cancelled_at')->nullable()->after('paid_at');
            $table->string('auto_cancellation_reason')->nullable()->after('auto_cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->dropColumn(['auto_cancelled_at', 'auto_cancellation_reason']);
        });
    }
};
