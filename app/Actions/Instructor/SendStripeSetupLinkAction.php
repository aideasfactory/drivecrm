<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Mail\StripeSetupLinkMail;
use App\Models\Instructor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class SendStripeSetupLinkAction
{
    /**
     * How long the emailed setup link stays valid.
     */
    public const EXPIRES_IN_DAYS = 7;

    /**
     * Email the instructor a signed link to connect Stripe themselves.
     *
     * The link points at our own `stripe.setup.start` route rather than at
     * Stripe: Stripe Account Links are single-use and expire within minutes,
     * so a fresh one is minted each time the instructor opens the email. The
     * Connect account itself is created on first open if they have none yet.
     *
     * @throws ValidationException when the instructor is already connected or has no email
     */
    public function __invoke(Instructor $instructor): string
    {
        if ($instructor->onboarding_complete) {
            throw ValidationException::withMessages([
                'instructor' => 'This instructor has already connected Stripe.',
            ]);
        }

        $email = $instructor->user?->email;

        if (! $email) {
            throw ValidationException::withMessages([
                'instructor' => 'This instructor has no email address to send the link to.',
            ]);
        }

        $setupUrl = URL::temporarySignedRoute(
            'stripe.setup.start',
            now()->addDays(self::EXPIRES_IN_DAYS),
            ['instructor' => $instructor->id],
        );

        Mail::to($email)->queue(new StripeSetupLinkMail($instructor, $setupUrl, self::EXPIRES_IN_DAYS));

        return $email;
    }
}
