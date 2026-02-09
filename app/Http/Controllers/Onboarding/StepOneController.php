<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StepOneRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepOneController extends Controller
{
    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');

        return Inertia::render('Onboarding/Step1', [
            'enquiry' => [
                'id' => $enquiry->id,
                'data' => $enquiry->data,
                'current_step' => $enquiry->current_step,
                'max_step_reached' => $enquiry->max_step_reached,
            ],
            'currentStep' => 1,
            'totalSteps' => 6,
            'stepData' => $enquiry->getStepData(1),
            'maxStepReached' => $enquiry->max_step_reached,
        ]);
    }

    public function store(StepOneRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        // Check for "booking for someone else" - for now we'll continue the normal flow
        // In the future this could redirect to a different flow if needed
        if (! empty($validated['booking_for_other'])) {
            // Log this for analytics or future separate flow
            \Log::info('Booking for someone else selected', [
                'enquiry_id' => $enquiry->id,
                'email' => $validated['email'],
            ]);
        }

        // Update enquiry with step 1 data
        $enquiry->setStepData(1, $validated);
        $enquiry->current_step = max($enquiry->current_step, 1);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 2);
        $enquiry->save();

        return redirect()->route('onboarding.step2', ['uuid' => $enquiry->id]);
    }
}
