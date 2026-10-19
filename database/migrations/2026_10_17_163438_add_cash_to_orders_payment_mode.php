<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash orders: imported pupils who pay their instructor in cash. Must run
     * before the code that books them is deployed.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_mode', ['upfront', 'weekly', 'imported', 'cash'])->default('upfront')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('payment_mode', ['upfront', 'weekly', 'imported'])->default('upfront')->change();
        });
    }
};
