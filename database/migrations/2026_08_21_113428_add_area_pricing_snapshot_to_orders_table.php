<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('area_outcode', 10)->nullable()->after('package_lessons_count');
            $table->integer('area_premium_pence')->default(0)->after('area_outcode');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['area_outcode', 'area_premium_pence']);
        });
    }
};
