<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HazardPerceptionVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HazardPerceptionVideo>
 */
class HazardPerceptionVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Car', 'ADI', 'Motorcycle', 'LGV-PCV']),
            'topic' => fake()->randomElement(['Junctions', 'Roundabouts', 'Pedestrians', 'Overtaking', 'Weather', 'Road Works', 'Cyclists', 'Animals', 'Parked Vehicles', 'School Zones']),
            'video_url' => 'hazard-perception/'.fake()->uuid().'.mp4',
            'duration_seconds' => fake()->numberBetween(30, 90),
            'is_double_hazard' => fake()->boolean(15),
            'thumbnail_url' => null,
            'has_recap' => false,
            'recap_video_url' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (HazardPerceptionVideo $video): void {
            $this->createZonesForHazard($video, 1);

            if ($video->is_double_hazard) {
                $this->createZonesForHazard($video, 2);
            }
        });
    }

    public function doubleHazard(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_double_hazard' => true,
        ]);
    }

    public function withRecap(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_recap' => true,
            'recap_video_url' => 'hazard-perception/recaps/'.fake()->uuid().'.mp4',
        ]);
    }

    /**
     * Create 5 contiguous one-second scoring zones (5 points first).
     */
    private function createZonesForHazard(HazardPerceptionVideo $video, int $hazardNumber): void
    {
        $offset = $hazardNumber === 1 ? 5 : 30;
        $start = fake()->randomFloat(2, $offset, $offset + 10);

        foreach ([5, 4, 3, 2, 1] as $index => $score) {
            $video->scoringZones()->create([
                'hazard_number' => $hazardNumber,
                'score' => $score,
                'start_seconds' => round($start + $index, 2),
                'end_seconds' => round($start + $index + 1, 2),
            ]);
        }
    }
}
