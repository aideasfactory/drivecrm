<?php

declare(strict_types=1);

namespace App\Models\Traits;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that are scoped to a team through their User relationship.
 *
 * Models using this trait must have a `user` BelongsTo relationship,
 * and the User model must have a `current_team_id` column.
 */
trait BelongsToTeam
{
    /**
     * Get the team this model belongs to (via its user).
     */
    public function team(): ?Team
    {
        return $this->user?->team;
    }

    /**
     * Scope query to a specific team (via the users table).
     */
    public function scopeForTeam(Builder $query, int|Team $team): Builder
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return $query->whereHas('user', function (Builder $userQuery) use ($teamId) {
            $userQuery->where('current_team_id', $teamId);
        });
    }

    /**
     * Scope query to the currently authenticated user's team.
     */
    public function scopeForCurrentTeam(Builder $query): Builder
    {
        $teamId = auth()->user()?->current_team_id;

        if ($teamId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->forTeam($teamId);
    }
}
