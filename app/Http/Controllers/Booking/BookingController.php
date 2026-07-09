<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
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

        // Google Ads landing URLs carry ?gclid=…; capture it here so it
        // survives the redirect into the step flow and can be forwarded to
        // downstream tools (Bird CRM, admin email, GTM) as source "Google ads".
        $gclid = trim((string) $request->query('gclid', ''));
        if ($gclid !== '') {
            $data['tracking'] = [
                'gclid' => $gclid,
                'source' => 'Google ads',
            ];
        }

        $enquiry = Enquiry::create([
            'data' => $data,
            'current_step' => 1,
            'max_step_reached' => 1,
        ]);

        return redirect()->route('booking.step1', ['uuid' => $enquiry->id]);
    }
}
