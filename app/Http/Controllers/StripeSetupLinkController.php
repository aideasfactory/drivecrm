<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Instructor\SendStripeSetupLinkAction;
use App\Models\Instructor;
use App\Services\InstructorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * Public entry point for the emailed Stripe setup link. The signature is the
 * access control — the instructor has no web session. Opening a valid link
 * mints a fresh Stripe Account Link via the same onboarding action the app
 * uses, then Stripe's refresh and return URLs land back here.
 */
class StripeSetupLinkController extends Controller
{
    public function __construct(
        protected InstructorService $instructorService,
    ) {}

    public function open(Request $request, Instructor $instructor): RedirectResponse|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->expired();
        }

        if ($instructor->isStripeConnected()) {
            return $this->page(
                'Stripe is already connected',
                'Your Stripe account is already connected. You can close this window.',
            );
        }

        try {
            $link = $this->instructorService->startStripeOnboarding(
                $instructor,
                $this->returnUrl($instructor),
                $this->refreshUrl($instructor),
            );
        } catch (RuntimeException) {
            return $this->unavailable();
        }

        return redirect()->away($link['url']);
    }

    /**
     * Stripe sends the instructor here when their single-use Account Link
     * expires mid-flow. Mint a fresh one and send them straight back.
     */
    public function refresh(Request $request, Instructor $instructor): RedirectResponse|Response
    {
        if (! $request->hasValidSignature()) {
            return $this->expired();
        }

        try {
            $link = $this->instructorService->startStripeOnboarding(
                $instructor,
                $this->returnUrl($instructor),
                $this->refreshUrl($instructor),
            );
        } catch (RuntimeException) {
            return $this->unavailable();
        }

        return redirect()->away($link['url']);
    }

    /**
     * Stripe sends the instructor here when they leave onboarding. Sync the
     * account so the instructor is marked connected, matching the webhook.
     */
    public function returned(Request $request, Instructor $instructor): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->expired();
        }

        try {
            $instructor = $this->instructorService->syncStripeAccountStatus($instructor);
        } catch (RuntimeException) {
            return $this->page(
                'We could not confirm Stripe setup',
                'Stripe may still finish connecting in the background. If your status does not show as connected, ask an administrator to send a new link.',
            );
        }

        if ($instructor->isStripeConnected()) {
            return $this->page(
                'Stripe is connected',
                'Your Stripe account is connected. You can close this window.',
            );
        }

        return $this->page(
            'Stripe setup is not finished',
            'Stripe still needs more information. Open the link from your email again, or ask an administrator to send a new one.',
        );
    }

    protected function returnUrl(Instructor $instructor): string
    {
        return URL::temporarySignedRoute(
            'stripe.setup.return',
            now()->addDays(SendStripeSetupLinkAction::LINK_EXPIRY_DAYS),
            ['instructor' => $instructor],
        );
    }

    protected function refreshUrl(Instructor $instructor): string
    {
        return URL::temporarySignedRoute(
            'stripe.setup.refresh',
            now()->addDays(SendStripeSetupLinkAction::LINK_EXPIRY_DAYS),
            ['instructor' => $instructor],
        );
    }

    protected function expired(): Response
    {
        return $this->page(
            'This Stripe setup link has expired',
            'Ask an administrator to send you a new link.',
            403,
        );
    }

    protected function unavailable(): Response
    {
        return $this->page(
            'We could not open Stripe setup',
            'Ask an administrator to send you a new link.',
            500,
        );
    }

    protected function page(string $title, string $message, int $status = 200): Response
    {
        return response()->view('stripe.setup-link', [
            'title' => $title,
            'message' => $message,
        ], $status);
    }
}
