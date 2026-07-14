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
        Schema::create('hazard_perception_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 100)->nullable()->index()
                ->comment('Topic filter used when the test was generated, null = whole bank');
            $table->unsignedTinyInteger('total_videos')
                ->comment('Number of clips selected at start');
            $table->unsignedSmallInteger('total_score')->default(0)
                ->comment('Sum of attempt scores, rolled up on completion');
            $table->unsignedSmallInteger('max_score')->default(0)
                ->comment('Fixed at start: 5 per single-hazard clip, 10 per double');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hazard_perception_tests');
    }
};
