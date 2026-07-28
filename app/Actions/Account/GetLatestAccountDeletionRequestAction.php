<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\AccountDeletionRequest;
use App\Models\User;

class GetLatestAccountDeletionRequestAction
{
    /**
     * Return the user's most recent deletion request, regardless of status.
     */
    public function __invoke(User $user): ?AccountDeletionRequest
    {
        return $user->accountDeletionRequests()
            ->latest('requested_at')
            ->latest('id')
            ->first();
    }
}
