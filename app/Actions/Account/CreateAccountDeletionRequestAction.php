<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AccountDeletionRequestStatus;
use App\Mail\AccountDeletionRequestedMail;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CreateAccountDeletionRequestAction
{
    /**
     * Grace period between the request and the hard delete.
     */
    public const GRACE_PERIOD_DAYS = 30;

    /**
     * Create a pending deletion request and queue the confirmation email.
     *
     * Tokens are deliberately NOT revoked — the user must be able to log in
     * during the grace period to cancel the request.
     */
    public function __invoke(User $user, ?string $reason = null): AccountDeletionRequest
    {
        $deletionRequest = AccountDeletionRequest::create([
            'user_id' => $user->id,
            'status' => AccountDeletionRequestStatus::PENDING,
            'reason' => $reason,
            'requested_at' => now(),
            'scheduled_for' => now()->addDays(self::GRACE_PERIOD_DAYS),
        ]);

        try {
            Mail::to($user->email)->queue(new AccountDeletionRequestedMail($user, $deletionRequest));
        } catch (\Throwable $e) {
            Log::error('Failed to queue account deletion confirmation email', [
                'user_id' => $user->id,
                'deletion_request_id' => $deletionRequest->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $deletionRequest;
    }
}
