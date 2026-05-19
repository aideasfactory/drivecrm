<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('instructor_id')
                ->constrained('vehicles')
                ->nullOnDelete();

            $table->index(['vehicle_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('instructor_finances', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'date']);
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn('vehicle_id');
        });
    }
};
