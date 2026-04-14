<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hazard_perception_videos', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 50)->index();
            $table->string('topic', 100)->index();
            $table->string('video_url');
            $table->unsignedInteger('duration_seconds');
            $table->decimal('hazard_1_start', 6, 2)->comment('Seconds into video when hazard 1 scoring window opens');
            $table->decimal('hazard_1_end', 6, 2)->comment('Seconds into video when hazard 1 scoring window closes');
            $table->decimal('hazard_2_start', 6, 2)->nullable()->comment('Seconds into video when hazard 2 scoring window opens (double hazard clips only)');
            $table->decimal('hazard_2_end', 6, 2)->nullable()->comment('Seconds into video when hazard 2 scoring window closes (double hazard clips only)');
            $table->boolean('is_double_hazard')->default(false)->index();
            $table->string('thumbnail_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hazard_perception_videos');
    }
};
