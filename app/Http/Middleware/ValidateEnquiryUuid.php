<?php

namespace App\Http\Middleware;

use App\Models\Enquiry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateEnquiryUuid
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('uuid');

        if (! $uuid) {
            return redirect()->route('onboarding.start');
        }

        $enquiry = Enquiry::find($uuid);

        if (! $enquiry) {
            // Invalid UUID — start fresh
            return redirect()->route('onboarding.start')
                ->with('error', 'Session not found. Please start again.');
        }

        // Share enquiry with all controllers
        $request->merge(['enquiry' => $enquiry]);

        return $next($request);
    }
}
