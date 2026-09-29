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
            $table->boolean('includes_test_pass_guarantee')->default(false)->after('discount_percentage');
            $table->unsignedInteger('test_pass_guarantee_pence')->default(0)->after('includes_test_pass_guarantee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['includes_test_pass_guarantee', 'test_pass_guarantee_pence']);
        });
    }
};
