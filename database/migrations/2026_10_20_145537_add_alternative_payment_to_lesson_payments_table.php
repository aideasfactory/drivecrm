<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alternative payment method: an instructor confirms that a pupil (whose
     * alternative payments an owner turned on) pays outside Stripe.
     *
     * `payment_method` is null for card / legacy rows and `alternative` once a
     * lesson is recorded as paid that way. The confirmed columns record who
     * accepted the risk and when; a confirmed payment is never invoiced or
     * chased. Must run before the code that reads it is deployed.
     */
    public function up(): void
    {
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('status');
            $table->timestamp('alternative_payment_confirmed_at')->nullable()->after('payment_method');
            $table->foreignId('alternative_payment_confirmed_by')
                ->nullable()
                ->after('alternative_payment_confirmed_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alternative_payment_confirmed_by');
            $table->dropColumn(['payment_method', 'alternative_payment_confirmed_at']);
        });
    }
};
