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
        Schema::create('hazard_perception_test_videos', function (Blueprint $table): void {
            $table->id();
            // Index/constraint names set explicitly — the auto-generated names
            // exceed MySQL's 64-character identifier limit for this table.
            $table->foreignId('hazard_perception_test_id')
                ->constrained(indexName: 'hp_test_videos_test_id_foreign')
                ->cascadeOnDelete();
            $table->foreignId('hazard_perception_video_id')
                ->constrained(indexName: 'hp_test_videos_video_id_foreign')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('position')
                ->comment('1-based playback order within the test');
            $table->timestamps();

            $table->unique(['hazard_perception_test_id', 'hazard_perception_video_id'], 'hp_test_videos_test_video_unique');
            $table->unique(['hazard_perception_test_id', 'position'], 'hp_test_videos_test_position_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hazard_perception_test_videos');
    }
};
