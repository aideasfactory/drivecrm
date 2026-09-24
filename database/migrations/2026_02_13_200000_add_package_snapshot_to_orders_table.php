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

        // Backfill existing orders from their related package.
        // A join update is not valid on SQLite (the test database).
        DB::table('orders')
            ->whereNotNull('package_id')
            ->orderBy('id')
            ->lazy()
            ->each(function (object $order): void {
                $package = DB::table('packages')->where('id', $order->package_id)->first();

                if ($package === null) {
                    return;
                }

                DB::table('orders')->where('id', $order->id)->update([
                    'package_name' => $package->name,
                    'package_total_price_pence' => $package->total_price_pence,
                    'package_lesson_price_pence' => $package->lesson_price_pence,
                    'package_lessons_count' => $package->lessons_count,
                ]);
            });
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
