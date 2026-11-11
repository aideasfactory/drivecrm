<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A package that has orders can no longer be deleted: the database refuses instead of
     * cascading the delete to orders, lessons, lesson payments and payouts.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->foreign('package_id')->references('id')->on('packages')->restrictOnDelete();
        });
    }

    /**
     * Put back the original cascading foreign key.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->foreign('package_id')->references('id')->on('packages')->cascadeOnDelete();
        });
    }
};
