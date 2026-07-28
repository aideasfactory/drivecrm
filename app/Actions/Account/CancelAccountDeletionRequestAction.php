<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\AccountDeletionRequestStatus;
use App\Mail\AccountDeletionCancelledMail;
use App\Models\AccountDeletionRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CancelAccountDeletionRequestAction
{
    /**
     * Cancel a pending deletion request and queue the confirmation email.
     */
    public function __invoke(AccountDeletionRequest $deletionRequest): AccountDeletionRequest
    {
        $deletionRequest->update([
            'status' => AccountDeletionRequestStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);

        $user = $deletionRequest->user;

        if ($user && $user->email) {
            try {
                Mail::to($user->email)->queue(new AccountDeletionCancelledMail($user));
            } catch (\Throwable $e) {
                Log::error('Failed to queue account deletion cancellation email', [
                    'user_id' => $user->id,
                    'deletion_request_id' => $deletionRequest->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $deletionRequest;
    }
}
