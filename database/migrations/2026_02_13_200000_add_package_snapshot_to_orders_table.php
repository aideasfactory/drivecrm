<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('package_name')->nullable()->after('package_id');
            $table->integer('package_total_price_pence')->nullable()->after('package_name');
            $table->integer('package_lesson_price_pence')->nullable()->after('package_total_price_pence');
            $table->integer('package_lessons_count')->nullable()->after('package_lesson_price_pence');
        });

        // Backfill existing orders from their related package
        DB::table('orders')
            ->join('packages', 'orders.package_id', '=', 'packages.id')
            ->update([
                'orders.package_name' => DB::raw('packages.name'),
                'orders.package_total_price_pence' => DB::raw('packages.total_price_pence'),
                'orders.package_lesson_price_pence' => DB::raw('packages.lesson_price_pence'),
                'orders.package_lessons_count' => DB::raw('packages.lessons_count'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'package_name',
                'package_total_price_pence',
                'package_lesson_price_pence',
                'package_lessons_count',
            ]);
        });
    }
};
