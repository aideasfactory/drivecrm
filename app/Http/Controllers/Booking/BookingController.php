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
        $enquiry = Enquiry::create([
            'data' => [
                'current_step' => 1,
                'steps' => [],
                'source' => 'booking',
            ],
            'current_step' => 1,
            'max_step_reached' => 1,
        ]);

        return redirect()->route('booking.step1', ['uuid' => $enquiry->id]);
    }
}
