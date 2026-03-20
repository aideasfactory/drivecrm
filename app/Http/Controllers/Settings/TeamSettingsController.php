<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTeamSettingsRequest;
use App\Models\Team;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamSettingsController extends Controller
{
    public function __construct(
        protected TeamService $teamService
    ) {}

    /**
     * Show the team settings page.
     */
    public function edit(Request $request): Response
    {
        $team = $request->user()->team;

        return Inertia::render('settings/Team', [
            'team' => $team,
            'settings' => [
                'primary_color' => $team?->getPrimaryColor(),
                'default_slot_duration_minutes' => $team?->getDefaultSlotDurationMinutes() ?? Team::SETTING_DEFAULTS['default_slot_duration_minutes'],
            ],
        ]);
    }

    /**
     * Update the team settings.
     */
    public function update(UpdateTeamSettingsRequest $request): RedirectResponse
    {
        $team = $request->user()->team;

        if (! $team) {
            abort(404, 'No team found for the current user.');
        }

        $settings = array_filter(
            $request->validated(),
            fn ($value) => $value !== null || $request->has(array_search($value, $request->validated()))
        );

        // Allow explicitly setting primary_color to null (to reset)
        if ($request->has('primary_color')) {
            $settings['primary_color'] = $request->validated('primary_color');
        }

        $this->teamService->updateSettings($team, $settings);

        return to_route('team-settings.edit');
    }
}
