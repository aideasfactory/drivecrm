<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HazardPerceptionTest;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardPerceptionTest>
 */
class HazardPerceptionTestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'topic' => null,
            'total_videos' => 14,
            'total_score' => 0,
            'max_score' => 70,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_score' => fake()->numberBetween(0, $attributes['max_score'] ?? 70),
            'completed_at' => now(),
        ]);
    }
}
