<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Public (signed-URL) landing routes for the Stripe setup link an admin
 * emails to an instructor. The instructor has no web session here, so the
 * signature is the access control. The signature is checked by hand rather
 * than with the `signed` middleware so an expired link shows a friendly page
 * instead of a 403.
 */
class StripeSetupLinkController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService
    ) {}

    /**
     * Mint a fresh Stripe Account Link and send the instructor into Stripe.
     * Also used as Stripe's refresh_url, so an expired Stripe link loops back
     * here for a new one.
     */
    public function start(Request $request, Instructor $instructor): View|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->page('expired');
        }

        if ($instructor->onboarding_complete) {
            return $this->page('complete');
        }

        try {
            $link = $this->instructorService->startStripeOnboarding(
                $instructor,
                URL::temporarySignedRoute('stripe.setup.complete', now()->addDay(), ['instructor' => $instructor->id]),
                URL::temporarySignedRoute('stripe.setup.start', now()->addDay(), ['instructor' => $instructor->id]),
            );
        } catch (RuntimeException $e) {
            Log::error('Stripe setup link: failed to start onboarding', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            return $this->page('error');
        }

        return redirect()->away($link['url']);
    }

    /**
     * Stripe sends the instructor here when they leave onboarding, finished
     * or not. Sync their status, then confirm or invite them to carry on.
     */
    public function complete(Request $request, Instructor $instructor): View
    {
        if (! $request->hasValidSignature()) {
            return $this->page('expired');
        }

        try {
            $instructor = $this->instructorService->syncStripeAccountStatus($instructor);
        } catch (RuntimeException) {
            // Best-effort — the account.updated webhook syncs status anyway.
        }

        if ($instructor->onboarding_complete) {
            return $this->page('complete');
        }

        return $this->page('incomplete', URL::temporarySignedRoute(
            'stripe.setup.start',
            now()->addDay(),
            ['instructor' => $instructor->id],
        ));
    }

    /**
     * @param  'complete'|'incomplete'|'expired'|'error'  $state
     */
    private function page(string $state, ?string $continueUrl = null): View
    {
        return view('stripe.setup-link', [
            'state' => $state,
            'continueUrl' => $continueUrl,
        ]);
    }
}
