<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HazardPerceptionAttempt;
use App\Models\HazardPerceptionVideo;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardPerceptionAttempt>
 */
class HazardPerceptionAttemptFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $h1Score = fake()->numberBetween(0, 5);

        return [
            'student_id' => Student::factory(),
            'hazard_perception_video_id' => HazardPerceptionVideo::factory(),
            'hazard_1_response_time' => fake()->randomFloat(2, 5, 60),
            'hazard_1_score' => $h1Score,
            'hazard_2_response_time' => null,
            'hazard_2_score' => null,
            'total_score' => $h1Score,
            'completed_at' => now(),
        ];
    }
}
