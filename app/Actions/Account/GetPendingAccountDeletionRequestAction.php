<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\AccountDeletionRequest;
use App\Models\User;

class GetPendingAccountDeletionRequestAction
{
    /**
     * Return the user's pending deletion request, if any.
     */
    public function __invoke(User $user): ?AccountDeletionRequest
    {
        return $user->accountDeletionRequests()
            ->pending()
            ->latest('requested_at')
            ->latest('id')
            ->first();
    }
}
