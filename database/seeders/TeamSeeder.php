<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TeamSeeder extends Seeder
{
    /**
     * Seed the teams table with the default Drive team.
     */
    public function run(): void
    {
        Team::firstOrCreate(
            ['id' => 1],
            [
                'uuid' => Str::uuid()->toString(),
                'name' => 'Drive',
                'settings' => [
                    'default_lesson_duration_minutes' => 60,
                    'primary_color' => null,
                    'default_slot_duration_minutes' => 120,
                ],
            ]
        );
    }
}
