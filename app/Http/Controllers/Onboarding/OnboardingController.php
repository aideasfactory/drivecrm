<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * Entry point — create new enquiry and redirect to step 1
     */
    public function start()
    {
        $enquiry = Enquiry::create([
            'data' => [
                'current_step' => 1,
                'steps' => [],
            ],
            'current_step' => 1,
            'max_step_reached' => 1,
        ]);

        return redirect()->route('onboarding.step1', ['uuid' => $enquiry->id]);
    }

    /**
     * Completion page after successful payment
     */
    public function complete(Request $request)
    {
        $enquiry = $request->get('enquiry');

        // Ensure payment was completed
        if ($enquiry->current_step < 6) {
            return redirect()->route('onboarding.step'.$enquiry->current_step, [
                'uuid' => $enquiry->id,
            ]);
        }

        return Inertia::render('Onboarding/Complete', [
            'enquiry' => $enquiry,
        ]);
    }
}
