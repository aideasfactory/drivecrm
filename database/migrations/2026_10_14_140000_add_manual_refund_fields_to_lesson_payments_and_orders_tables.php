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
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->timestamp('manually_refunded_at')->nullable()->after('stripe_charge_id');
            $table->foreignId('manually_refunded_by_user_id')->nullable()->after('manually_refunded_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('manually_refunded_at')->nullable()->after('booked_by_user_id');
            $table->foreignId('manually_refunded_by_user_id')->nullable()->after('manually_refunded_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manually_refunded_by_user_id');
            $table->dropColumn('manually_refunded_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manually_refunded_by_user_id');
            $table->dropColumn('manually_refunded_at');
        });
    }
};
