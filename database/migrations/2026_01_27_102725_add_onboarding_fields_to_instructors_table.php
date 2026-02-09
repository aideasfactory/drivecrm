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
            $table->text('bio')->nullable();
            $table->string('status', 50)->default('active');
            $table->string('pdi_status', 50)->nullable();
            $table->boolean('priority')->default(false);
            $table->text('address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructors', function (Blueprint $table) {
            $table->dropColumn([
                'bio',
                'status',
                'pdi_status',
                'priority',
                'address',
            ]);
        });
    }
};
