<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StepOneRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StepOneController extends Controller
{
    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');

        return Inertia::render('Booking/Step1', [
            'enquiry' => [
                'id' => $enquiry->id,
                'data' => $enquiry->data,
                'current_step' => $enquiry->current_step,
                'max_step_reached' => $enquiry->max_step_reached,
            ],
            'currentStep' => 1,
            'totalSteps' => 2,
            'stepData' => $enquiry->getStepData(1),
            'maxStepReached' => $enquiry->max_step_reached,
        ]);
    }

    public function store(StepOneRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = $request->validated();

        // Normalize postcode: strip spaces and re-insert before the 3-char inward code
        $stripped = strtoupper(preg_replace('/\s+/', '', $validated['postcode']));
        $validated['postcode'] = substr($stripped, 0, -3).' '.substr($stripped, -3);

        $enquiry->setStepData(1, $validated);
        $enquiry->current_step = max($enquiry->current_step, 1);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 2);
        $enquiry->save();

        return redirect()->route('booking.step2', ['uuid' => $enquiry->id]);
    }
}
