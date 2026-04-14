<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HazardPerceptionVideo>
 */
class HazardPerceptionVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isDouble = fake()->boolean(15);
        $duration = fake()->numberBetween(30, 90);
        $h1Start = fake()->randomFloat(2, 5, $duration - 15);
        $h1End = $h1Start + fake()->randomFloat(2, 3, 8);

        $data = [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Car', 'ADI', 'Motorcycle', 'LGV-PCV']),
            'topic' => fake()->randomElement(['Junctions', 'Roundabouts', 'Pedestrians', 'Overtaking', 'Weather', 'Road Works', 'Cyclists', 'Animals', 'Parked Vehicles', 'School Zones']),
            'video_url' => 'hazard-perception/'.fake()->uuid().'.mp4',
            'duration_seconds' => $duration,
            'hazard_1_start' => $h1Start,
            'hazard_1_end' => $h1End,
            'hazard_2_start' => null,
            'hazard_2_end' => null,
            'is_double_hazard' => $isDouble,
            'thumbnail_url' => null,
        ];

        if ($isDouble) {
            $h2Start = $h1End + fake()->randomFloat(2, 5, 15);
            $data['hazard_2_start'] = min($h2Start, $duration - 5);
            $data['hazard_2_end'] = min($h2Start + fake()->randomFloat(2, 3, 8), (float) $duration);
        }

        return $data;
    }

    public function doubleHazard(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_double_hazard' => true,
            'hazard_2_start' => $attributes['hazard_1_end'] + 5.0,
            'hazard_2_end' => $attributes['hazard_1_end'] + 12.0,
        ]);
    }
}
