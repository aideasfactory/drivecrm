<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HazardPerceptionScoringZone;
use App\Models\HazardPerceptionVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardPerceptionScoringZone>
 */
class HazardPerceptionScoringZoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->randomFloat(2, 5, 60);

        return [
            'hazard_perception_video_id' => HazardPerceptionVideo::factory(),
            'hazard_number' => 1,
            'score' => fake()->numberBetween(1, 5),
            'start_seconds' => $start,
            'end_seconds' => round($start + 1, 2),
        ];
    }
}
