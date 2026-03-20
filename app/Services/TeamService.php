<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Team\UpdateTeamSettingsAction;
use App\Models\Team;

class TeamService extends BaseService
{
    public function __construct(
        protected UpdateTeamSettingsAction $updateTeamSettings
    ) {}

    /**
     * Update the team's settings.
     *
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(Team $team, array $settings): Team
    {
        $team = ($this->updateTeamSettings)($team, $settings);

        $this->invalidateTeamCache($team);

        return $team;
    }

    /**
     * Invalidate all cached data for a team.
     */
    public function invalidateTeamCache(Team $team): void
    {
        $this->invalidate(
            $this->cacheKey('team', $team->id, 'settings')
        );
    }
}
