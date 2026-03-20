<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Team;

class UpdateTeamSettingsAction
{
    /**
     * Update team settings by merging the provided values into the existing settings JSON.
     *
     * @param  array<string, mixed>  $settings  Key-value pairs to merge into the team's settings
     */
    public function __invoke(Team $team, array $settings): Team
    {
        $currentSettings = $team->settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);

        $team->update(['settings' => $mergedSettings]);

        return $team->fresh();
    }
}
