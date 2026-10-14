<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\MarketingAttribution;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Entry point — create new enquiry and redirect to step 1.
     */
    public function start(Request $request)
    {
        $data = [
            'current_step' => 1,
            'steps' => [],
            'source' => 'booking',
        ];

        // Ad landing URLs carry gclid/gbraid/wbraid/fbclid and utm_* params.
        // Store them for downstream tools (Bird CRM, admin email) and keep
        // them on the step 1 URL so GTM sees them on its first page load.
        $attribution = MarketingAttribution::fromRequest($request);
        $tracking = MarketingAttribution::enquiryTracking($attribution);
        if ($tracking !== null) {
            $data['tracking'] = $tracking;
        }

        $enquiry = Enquiry::create([
            'data' => $data,
            'current_step' => 1,
            'max_step_reached' => 1,
        ]);

        return redirect()->route('booking.step1', ['uuid' => $enquiry->id, ...$attribution]);
    }
}
