<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateStepAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $enquiry = $request->get('enquiry');
        $requestedStep = $this->extractStepFromRoute($request);

        if ($requestedStep === null) {
            return $next($request);
        }

        $currentStep = $enquiry->current_step;
        $maxAllowedStep = $currentStep + 1;

        // Users can go back to any completed step
        // Users can advance to the next step only
        if ($requestedStep > $maxAllowedStep) {
            return redirect()->route('onboarding.step'.$currentStep, [
                'uuid' => $enquiry->id,
            ])->with('error', 'Please complete the current step first.');
        }

        return $next($request);
    }

    private function extractStepFromRoute(Request $request): ?int
    {
        $uri = $request->path();

        if (preg_match('/step\/(\d+)/', $uri, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
