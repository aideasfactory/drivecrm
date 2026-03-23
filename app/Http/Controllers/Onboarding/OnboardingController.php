<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Enquiry;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    /**
     * Entry point — create new enquiry and redirect to step 1.
     * Accepts optional ?discount=<uuid> parameter.
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

        $enquiry = Enquiry::create([
            'data' => $data,
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
