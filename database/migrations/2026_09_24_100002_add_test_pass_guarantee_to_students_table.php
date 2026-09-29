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
        Schema::table('students', function (Blueprint $table) {
            $table->timestamp('test_pass_guarantee_at')->nullable()->after('inactive_reason');
            $table->foreignId('test_pass_guarantee_order_id')
                ->nullable()
                ->after('test_pass_guarantee_at')
                ->constrained('orders')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('test_pass_guarantee_order_id');
            $table->dropColumn('test_pass_guarantee_at');
        });
    }
};
