<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HazardPerceptionVideo;
use Illuminate\Database\Seeder;

class HazardPerceptionVideoSeeder extends Seeder
{
    public function run(): void
    {
        HazardPerceptionVideo::updateOrCreate(
            ['video_url' => 'https://player.vimeo.com/video/347119375'],
            [
                'title' => 'Country Road Hazard',
                'description' => 'A hazard perception clip on a country road. Identify the developing hazard as it appears.',
                'category' => 'Car',
                'topic' => 'Junctions',
                'duration_seconds' => 75,
                'hazard_1_start' => 50.00,
                'hazard_1_end' => 60.00,
                'hazard_2_start' => null,
                'hazard_2_end' => null,
                'is_double_hazard' => false,
                'thumbnail_url' => 'https://media.gettyimages.com/id/1501533241/video/white-rabbit-walking-cautiously-across-the-field.jpg?s=640x640&k=20&c=6r3yT06BkVWUYDQa2Q78Yjc_qjoJAHcIOpwvvi2U_DI=',
            ],
        );
    }
}
