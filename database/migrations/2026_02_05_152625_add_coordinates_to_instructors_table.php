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
        Schema::table('instructors', function (Blueprint $table) {
            $table->decimal('latitude', 10, 8)->nullable()->after('postcode');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->index(['latitude', 'longitude'], 'instructors_coordinates_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropIndex('instructors_coordinates_index');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
