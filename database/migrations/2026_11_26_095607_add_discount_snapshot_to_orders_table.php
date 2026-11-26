<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of the discount on each order: the code (or label for
     * link-only codes) and the amount taken off the package price.
     * Must be migrated before the code that writes them ships.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_code', 255)->nullable()->after('discount_percentage');
            $table->unsignedInteger('discount_pence')->nullable()->after('discount_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_code', 'discount_pence']);
        });
    }
};
