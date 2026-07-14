<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The column-add and FK-add are separate steps: an earlier mis-ordered
     * run of this migration added the column but failed on the FK (the
     * hazard_perception_tests table did not exist yet), so the column may
     * already be present without its constraint.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('hazard_perception_attempts', 'hazard_perception_test_id')) {
            Schema::table('hazard_perception_attempts', function (Blueprint $table): void {
                $table->unsignedBigInteger('hazard_perception_test_id')
                    ->nullable()
                    ->after('hazard_perception_video_id')
                    ->comment('Null = practice attempt, set = part of a test session');
            });
        }

        Schema::table('hazard_perception_attempts', function (Blueprint $table): void {
            $table->foreign('hazard_perception_test_id', 'hp_attempts_test_id_foreign')
                ->references('id')
                ->on('hazard_perception_tests')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hazard_perception_attempts', function (Blueprint $table): void {
            $table->dropForeign('hp_attempts_test_id_foreign');
            $table->dropColumn('hazard_perception_test_id');
        });
    }
};
