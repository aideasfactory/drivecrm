<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\StepOneRequest;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;

class StepOneController extends Controller
{
    public function show(Request $request)
    {
        $enquiry = $request->get('enquiry');

        // Catch click IDs on a step 1 URL that bypassed /booking (e.g. a reload
        // or shared link carrying ?gclid=…). Existing IDs are never cleared.
        $enquiry->fillAdClickIds($request->query());
        if ($enquiry->isDirty(Enquiry::AD_CLICK_ID_FIELDS)) {
            $enquiry->save();
        }

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
            'adClickIds' => $enquiry->only(Enquiry::AD_CLICK_ID_FIELDS),
        ]);
    }

    public function store(StepOneRequest $request)
    {
        $enquiry = $request->get('enquiry');
        $validated = Arr::except($request->validated(), Enquiry::AD_CLICK_ID_FIELDS);

        // Normalize postcode: strip spaces and re-insert before the 3-char inward code
        $stripped = strtoupper(preg_replace('/\s+/', '', $validated['postcode']));
        $validated['postcode'] = substr($stripped, 0, -3).' '.substr($stripped, -3);

        // Treat submission as bundled consent to marketing per client instruction.
        // The small print under the submit button is the consent notice.
        $validated['marketing_consent'] = true;

        $enquiry->setStepData(1, $validated);
        $enquiry->fillAdClickIds($request->validated());
        $enquiry->current_step = max($enquiry->current_step, 1);
        $enquiry->max_step_reached = max($enquiry->max_step_reached, 2);
        $enquiry->privacy_consent = (bool) ($validated['privacy_consent'] ?? false);
        $enquiry->marketing_consent = true;
        $enquiry->consented_at = now();
        $enquiry->save();

        return redirect()->route('booking.step2', ['uuid' => $enquiry->id]);
    }
}
