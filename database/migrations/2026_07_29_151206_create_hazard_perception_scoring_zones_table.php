<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard against a previous partial run — the FK alter can fail after
        // the create, leaving the table behind without the migration recorded.
        Schema::dropIfExists('hazard_perception_scoring_zones');

        Schema::create('hazard_perception_scoring_zones', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hazard_perception_video_id');
            // Custom FK name — the auto-generated one exceeds MySQL's 64-char identifier limit.
            $table->foreign('hazard_perception_video_id', 'hp_scoring_zones_video_id_foreign')
                ->references('id')
                ->on('hazard_perception_videos')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('hazard_number')->default(1)->comment('1 or 2 (double hazard clips)');
            $table->unsignedTinyInteger('score')->comment('Points awarded for a tap inside this zone (1-5)');
            $table->decimal('start_seconds', 6, 2)->comment('Seconds into video when this scoring zone opens');
            $table->decimal('end_seconds', 6, 2)->comment('Seconds into video when this scoring zone closes');
            $table->timestamps();

            $table->unique(
                ['hazard_perception_video_id', 'hazard_number', 'score'],
                'hp_scoring_zones_video_hazard_score_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hazard_perception_scoring_zones');
    }
};
