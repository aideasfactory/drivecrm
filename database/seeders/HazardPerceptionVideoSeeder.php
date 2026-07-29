<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HazardPerceptionVideo;
use Illuminate\Database\Seeder;

class HazardPerceptionVideoSeeder extends Seeder
{
    public function run(): void
    {
        $video = HazardPerceptionVideo::updateOrCreate(
            ['video_url' => 'https://player.vimeo.com/video/347119375'],
            [
                'title' => 'Country Road Hazard',
                'description' => 'A hazard perception clip on a country road. Identify the developing hazard as it appears.',
                'category' => 'Car',
                'topic' => 'Junctions',
                'duration_seconds' => 75,
                'is_double_hazard' => false,
                'has_recap' => false,
                'thumbnail_url' => 'https://media.gettyimages.com/id/1501533241/video/white-rabbit-walking-cautiously-across-the-field.jpg?s=640x640&k=20&c=6r3yT06BkVWUYDQa2Q78Yjc_qjoJAHcIOpwvvi2U_DI=',
            ],
        );

        // Five contiguous 2-second scoring zones across the 50s-60s hazard window.
        foreach ([5, 4, 3, 2, 1] as $index => $score) {
            $video->scoringZones()->updateOrCreate(
                ['hazard_number' => 1, 'score' => $score],
                [
                    'start_seconds' => 50.00 + ($index * 2),
                    'end_seconds' => 50.00 + (($index + 1) * 2),
                ],
            );
        }
    }
}
