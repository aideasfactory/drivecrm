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
            $table->uuid('discount_code_id')->nullable()->after('stripe_checkout_session_id');
            $table->unsignedTinyInteger('discount_percentage')->nullable()->after('discount_code_id');

            $table->foreign('discount_code_id')
                ->references('id')
                ->on('discount_codes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['discount_code_id']);
            $table->dropColumn(['discount_code_id', 'discount_percentage']);
        });
    }
};
