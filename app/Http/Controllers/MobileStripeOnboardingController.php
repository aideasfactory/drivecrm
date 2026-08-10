<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Public (signed-URL) landing routes for the mobile Stripe Connect onboarding
 * flow. The in-app browser has no web session, so these routes sit outside
 * auth middleware — the signature is the access control, and the app
 * independently verifies completion via GET /api/v1/instructor/stripe/status.
 */
class MobileStripeOnboardingController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Stripe sends the instructor here when they exit the onboarding flow
     * (finished or not). Sync status, then hand control back to the app.
     */
    public function handleReturn(Instructor $instructor): View
    {
        try {
            $this->instructorService->syncStripeAccountStatus($instructor);
        } catch (RuntimeException) {
            // Best-effort — the app re-syncs via the status endpoint anyway.
        }

        return view('stripe.mobile-onboarding-return', [
            'deepLink' => config('services.stripe.mobile_return_deeplink').'?status=return',
        ]);
    }

    /**
     * Stripe sends the instructor here when their single-use onboarding link
     * has expired. Mint a fresh link and bounce straight back into Stripe so
     * they never fall out of the flow.
     */
    public function handleRefresh(Instructor $instructor): RedirectResponse
    {
        try {
            $link = $this->instructorService->startStripeOnboarding(
                $instructor,
                URL::signedRoute('stripe.mobile.return', ['instructor' => $instructor->id]),
                URL::signedRoute('stripe.mobile.refresh', ['instructor' => $instructor->id]),
            );
        } catch (RuntimeException) {
            return redirect()->to(
                config('services.stripe.mobile_return_deeplink').'?status=refresh_failed'
            );
        }

        return redirect()->to($link['url']);
    }
}
