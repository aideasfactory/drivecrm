<?php

namespace App\Http\Middleware;

use App\Models\Enquiry;
use App\Support\MarketingParams;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBookingEnquiryUuid
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('uuid');

        if (! $uuid) {
            return redirect()->route('booking.start', MarketingParams::fromRequest($request));
        }

        $enquiry = Enquiry::find($uuid);

        if (! $enquiry) {
            return redirect()->route('booking.start', MarketingParams::fromRequest($request))
                ->with('error', 'Session not found. Please start again.');
        }

        $request->merge(['enquiry' => $enquiry]);

        return $next($request);
    }
}
