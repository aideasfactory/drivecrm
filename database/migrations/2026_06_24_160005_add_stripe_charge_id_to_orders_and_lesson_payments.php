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
            $table->string('stripe_charge_id')->nullable()->after('stripe_payment_intent_id');
        });

        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->after('stripe_invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stripe_charge_id');
        });

        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->dropColumn('stripe_charge_id');
        });
    }
};
