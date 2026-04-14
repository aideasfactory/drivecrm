<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hazard_perception_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('hazard_perception_video_id')->constrained('hazard_perception_videos')->cascadeOnDelete();
            $table->decimal('hazard_1_response_time', 6, 2)->nullable()->comment('Seconds into video when student flagged hazard 1');
            $table->unsignedTinyInteger('hazard_1_score')->default(0)->comment('Score 0-5 for hazard 1');
            $table->decimal('hazard_2_response_time', 6, 2)->nullable()->comment('Seconds into video when student flagged hazard 2');
            $table->unsignedTinyInteger('hazard_2_score')->nullable()->comment('Score 0-5 for hazard 2 (null if single hazard clip)');
            $table->unsignedTinyInteger('total_score')->default(0)->comment('Combined score (max 5 for single, max 10 for double)');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'hazard_perception_video_id'], 'hp_attempts_student_video_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hazard_perception_attempts');
    }
};
