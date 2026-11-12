<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mileage_logs', function (Blueprint $table) {
            $table->unsignedInteger('end_mileage')->nullable()->change();
        });
    }

    /**
     * Incomplete trips become zero-mile trips (end = start) so the NOT NULL change can apply.
     */
    public function down(): void
    {
        DB::table('mileage_logs')
            ->whereNull('end_mileage')
            ->update(['end_mileage' => DB::raw('start_mileage'), 'miles' => 0]);

        Schema::table('mileage_logs', function (Blueprint $table) {
            $table->unsignedInteger('end_mileage')->nullable(false)->change();
        });
    }
};
