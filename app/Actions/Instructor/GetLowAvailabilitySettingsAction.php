<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Models\Team;

class GetLowAvailabilitySettingsAction
{
    public const DEFAULT_WEEKS = 4;

    public const DEFAULT_MIN_SLOTS_PER_WEEK = 1;

    /**
     * The low availability threshold, stored under `low_availability` in the
     * default team's settings. An active, Stripe-connected instructor is
     * flagged when any one of the next `weeks` weeks has fewer than
     * `min_slots_per_week` open slots. Defaults: 4 weeks, 1 slot (i.e. a week
     * with no availability at all).
     *
     * @return array{weeks: int, min_slots_per_week: int}
     */
    public function __invoke(): array
    {
        $settings = Team::query()->find(Team::DEFAULT_TEAM_ID)?->settings['low_availability'] ?? [];

        return [
            'weeks' => max(1, (int) ($settings['weeks'] ?? self::DEFAULT_WEEKS)),
            'min_slots_per_week' => max(1, (int) ($settings['min_slots_per_week'] ?? self::DEFAULT_MIN_SLOTS_PER_WEEK)),
        ];
    }
}
