<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Account\CancelAccountDeletionRequestAction;
use App\Actions\Account\CreateAccountDeletionRequestAction;
use App\Actions\Account\GetLatestAccountDeletionRequestAction;
use App\Actions\Account\GetPendingAccountDeletionRequestAction;
use App\Actions\Account\ProcessAccountDeletionAction;
use App\Models\AccountDeletionRequest;
use App\Models\User;

class AccountDeletionService extends BaseService
{
    public function __construct(
        protected CreateAccountDeletionRequestAction $createDeletionRequest,
        protected GetLatestAccountDeletionRequestAction $getLatestDeletionRequest,
        protected GetPendingAccountDeletionRequestAction $getPendingDeletionRequest,
        protected CancelAccountDeletionRequestAction $cancelDeletionRequest,
        protected ProcessAccountDeletionAction $processDeletion,
    ) {}

    /**
     * The user's most recent deletion request, regardless of status.
     *
     * Deliberately uncached — the mobile app uses this to reconcile deletion
     * state across devices, so it must always reflect the live row.
     */
    public function getLatestRequest(User $user): ?AccountDeletionRequest
    {
        return ($this->getLatestDeletionRequest)($user);
    }

    /**
     * The user's pending deletion request, if any.
     */
    public function getPendingRequest(User $user): ?AccountDeletionRequest
    {
        return ($this->getPendingDeletionRequest)($user);
    }

    /**
     * Create a pending deletion request (30-day grace period) and send the
     * confirmation email.
     */
    public function requestDeletion(User $user, ?string $reason = null): AccountDeletionRequest
    {
        return ($this->createDeletionRequest)($user, $reason);
    }

    /**
     * Cancel a pending deletion request and send the confirmation email.
     */
    public function cancelRequest(AccountDeletionRequest $deletionRequest): AccountDeletionRequest
    {
        return ($this->cancelDeletionRequest)($deletionRequest);
    }

    /**
     * Hard-process a due deletion request (anonymise user, revoke tokens,
     * detach an instructor's students, mark completed).
     */
    public function processDueRequest(AccountDeletionRequest $deletionRequest): void
    {
        ($this->processDeletion)($deletionRequest);
    }
}
