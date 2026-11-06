<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Support\MarketingParams;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

        // Google Ads landing URLs carry ?gclid=… (or gbraid/wbraid for iOS);
        // capture them here so they survive the redirect into the step flow and
        // can be forwarded to downstream tools (Bird CRM, admin email, GTM) as
        // source "Google ads".
        $marketingParams = MarketingParams::fromRequest($request);
        $adClickIds = Arr::only($marketingParams, Enquiry::AD_CLICK_ID_FIELDS);
        if ($adClickIds !== []) {
            $data['tracking'] = array_filter([
                'gclid' => $adClickIds['gclid'] ?? null,
                'source' => 'Google ads',
            ]);
        }

        $enquiry = new Enquiry([
            'data' => $data,
            'current_step' => 1,
            'max_step_reached' => 1,
        ]);
        $enquiry->fillAdClickIds($adClickIds);
        $enquiry->save();

        // Pass ad-click/UTM params through so GTM sees them on the landing page
        return redirect()->route('booking.step1', ['uuid' => $enquiry->id, ...$marketingParams]);
    }
}
