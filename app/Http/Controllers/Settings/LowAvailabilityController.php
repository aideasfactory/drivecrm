<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Instructor\GetLowAvailabilitySettingsAction;
use App\Actions\Instructor\UpdateLowAvailabilitySettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LowAvailabilitySettingsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LowAvailabilityController extends Controller
{
    /**
     * Show the instructor low availability threshold settings page.
     */
    public function edit(Request $request, GetLowAvailabilitySettingsAction $getLowAvailabilitySettings): Response
    {
        abort_if($request->user()->isRestrictedOwner(), 403, 'Your owner access does not include this setting.');

        return Inertia::render('settings/LowAvailability', [
            'settings' => $getLowAvailabilitySettings(),
        ]);
    }

    /**
     * Update the instructor low availability threshold.
     */
    public function update(LowAvailabilitySettingsUpdateRequest $request, UpdateLowAvailabilitySettingsAction $updateLowAvailabilitySettings): RedirectResponse
    {
        $updateLowAvailabilitySettings(
            (int) $request->validated('weeks'),
            (int) $request->validated('min_slots_per_week'),
        );

        return back();
    }
}
