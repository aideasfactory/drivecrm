<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Shared\LogActivityAction;
use App\Mail\InstructorStripeSetupLinkMail;
use App\Models\Instructor;
use App\Services\StripeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendStripeSetupLinkAction
{
    /**
     * How long the emailed setup URL stays valid. Stripe Account Links expire
     * after a few minutes, so the email carries this signed app URL instead.
     * Opening it mints a fresh Account Link.
     */
    public const LINK_EXPIRY_DAYS = 7;

    public function __construct(
        protected StripeService $stripeService,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Create the instructor's Express account when they don't have one, then
     * email a 7-day signed setup URL.
     *
     * The account is created with StripeService::createConnectAccount, the
     * same call app onboarding uses: an Express account in GB with the
     * transfers capability and the instructor's email.
     *
     * Never throws — returns false when the account cannot be created or the
     * email cannot be queued.
     */
    public function __invoke(Instructor $instructor): bool
    {
        $instructor->loadMissing('user');
        $user = $instructor->user;

        if ($instructor->isStripeConnected()) {
            return false;
        }

        if (! $user || ! $user->email) {
            Log::warning('Cannot send Stripe setup link: missing user or email', [
                'instructor_id' => $instructor->id,
            ]);

            return false;
        }

        if (! $this->ensureConnectAccount($instructor)) {
            return false;
        }

        try {
            $setupUrl = URL::temporarySignedRoute(
                'stripe.setup',
                now()->addDays(self::LINK_EXPIRY_DAYS),
                ['instructor' => $instructor],
            );

            Mail::to($user->email)->queue(new InstructorStripeSetupLinkMail(
                user: $user,
                setupUrl: $setupUrl,
                expiresInDays: self::LINK_EXPIRY_DAYS,
            ));

            ($this->logActivity)(
                $instructor,
                "Stripe link sent to {$user->email}",
                'notification',
                [
                    'type' => 'stripe_setup_link',
                    'recipient_email' => $user->email,
                ],
                'Stripe link sent',
            );

            Log::info('Stripe setup link queued', [
                'instructor_id' => $instructor->id,
                'recipient_email' => $user->email,
                'stripe_account_id' => $instructor->stripe_account_id,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send Stripe setup link', [
                'instructor_id' => $instructor->id,
                'recipient_email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create the Express account once and store its id before any email is sent.
     * The row lock stops two staff clicks creating two Stripe accounts.
     */
    private function ensureConnectAccount(Instructor $instructor): bool
    {
        if ($instructor->stripe_account_id) {
            return true;
        }

        try {
            return DB::transaction(function () use ($instructor): bool {
                $locked = Instructor::query()->whereKey($instructor->id)->lockForUpdate()->first();

                if (! $locked) {
                    return false;
                }

                if ($locked->stripe_account_id) {
                    $instructor->stripe_account_id = $locked->stripe_account_id;

                    return true;
                }

                $locked->load('user');
                $accountResult = $this->stripeService->createConnectAccount($locked);

                if (! $accountResult['success']) {
                    return false;
                }

                $locked->stripe_account_id = $accountResult['account_id'];
                $locked->save();
                $instructor->stripe_account_id = $locked->stripe_account_id;

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Failed to create Stripe account for setup link', [
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
