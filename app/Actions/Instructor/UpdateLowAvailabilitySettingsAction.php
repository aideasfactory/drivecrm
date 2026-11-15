<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Team;
use Illuminate\Support\Str;

class UpdateLowAvailabilitySettingsAction
{
    /**
     * Save the low availability threshold into the default team's settings,
     * keeping any other settings already stored there.
     */
    public function __invoke(int $weeks, int $minSlotsPerWeek): void
    {
        $team = Team::query()->find(Team::DEFAULT_TEAM_ID)
            ?? (new Team)->forceFill([
                'id' => Team::DEFAULT_TEAM_ID,
                'uuid' => Str::uuid()->toString(),
                'name' => 'Drive',
            ]);

        $team->settings = [
            ...($team->settings ?? []),
            'low_availability' => [
                'weeks' => $weeks,
                'min_slots_per_week' => $minSlotsPerWeek,
            ],
        ];

        $team->save();
    }
}
