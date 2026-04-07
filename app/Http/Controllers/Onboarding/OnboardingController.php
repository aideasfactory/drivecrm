<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Enquiry;
use App\Models\Instructor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * Entry point — create new enquiry and redirect to step 1.
     * Accepts optional query parameters:
     *   ?discount=<uuid>
     *   ?first_name=<string>&last_name=<string>&email=<string> — prefill step 1
     *   ?instructor_id=<int> — prefill step 2 (bypass instructor selection)
     */
    public function start(Request $request)
    {
        $data = [
            'current_step' => 1,
            'steps' => [],
        ];

        // Validate and attach discount code if provided
        $discountUuid = $request->query('discount');
        if ($discountUuid) {
            $discountCode = DiscountCode::query()
                ->where('id', $discountUuid)
                ->where('active', true)
                ->first();

            if ($discountCode) {
                $data['discount'] = [
                    'id' => $discountCode->id,
                    'label' => $discountCode->label,
                    'percentage' => $discountCode->percentage,
                ];
            }
        }

        // Store prefill data for reuse from lessons page
        $prefill = [];
        if ($request->filled('first_name')) {
            $prefill['first_name'] = $request->query('first_name');
        }
        if ($request->filled('last_name')) {
            $prefill['last_name'] = $request->query('last_name');
        }
        if ($request->filled('email')) {
            $prefill['email'] = $request->query('email');
        }
        if ($request->filled('instructor_id')) {
            $instructor = Instructor::find($request->query('instructor_id'));
            if ($instructor) {
                $prefill['instructor_id'] = $instructor->id;
            }
        }

        if (! empty($prefill)) {
            $data['prefill'] = $prefill;
        }

        $maxStep = 1;

        // If instructor_id is prefilled, auto-populate step 2 data and advance
        if (isset($prefill['instructor_id'])) {
            $data['steps']['step2'] = [
                'instructor_id' => $prefill['instructor_id'],
            ];
            $maxStep = 3;
        }

        $enquiry = Enquiry::create([
            'data' => $data,
            'current_step' => 1,
            'max_step_reached' => $maxStep,
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
