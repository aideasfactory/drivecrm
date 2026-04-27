<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Models\Enquiry;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SendOnboardingWelcomeAction
{
    /**
     * Send the temporary password email to a first-time onboarding pupil.
     *
     * Reads is_new_user and encrypted temporary_password from the enquiry step 6 data.
     * Only sends if the user is new and the email has not already been sent.
     * Marks the enquiry as sent to prevent double-sending.
     */
    public function __invoke(Enquiry $enquiry): void
    {
        $step6 = $enquiry->getStepData(6) ?? [];

        if (empty($step6['is_new_user'])) {
            return;
        }

        if (! empty($step6['temp_password_sent'])) {
            Log::info('Onboarding welcome email already sent — skipping', [
                'enquiry_id' => $enquiry->id,
            ]);

            return;
        }

        if (empty($step6['temporary_password']) || empty($step6['user_id'])) {
            Log::warning('Missing temporary password or user_id in enquiry — cannot send welcome email', [
                'enquiry_id' => $enquiry->id,
            ]);

            return;
        }

        $user = User::find($step6['user_id']);

        if (! $user) {
            Log::warning('User not found for onboarding welcome email', [
                'enquiry_id' => $enquiry->id,
                'user_id' => $step6['user_id'],
            ]);

            return;
        }

        try {
            $temporaryPassword = Crypt::decryptString($step6['temporary_password']);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt temporary password for onboarding welcome email', [
                'enquiry_id' => $enquiry->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $user->notify(new OnboardingWelcomeNotification($temporaryPassword));

        // Mark as sent to prevent double-sending
        $enquiry->setStepData(6, array_merge($step6, [
            'temp_password_sent' => true,
            'temporary_password' => null, // Clear the encrypted password
        ]));
        $enquiry->save();

        Log::info('Onboarding welcome email sent to first-time pupil', [
            'enquiry_id' => $enquiry->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
