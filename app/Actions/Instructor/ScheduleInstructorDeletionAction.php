<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Account\CreateAccountDeletionRequestAction;
use App\Actions\Account\GetPendingAccountDeletionRequestAction;
use App\Enums\AccountDeletionRequestStatus;
use App\Enums\InstructorStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ScheduleInstructorDeletionAction
{
    public function __construct(
        protected GetPendingAccountDeletionRequestAction $getPendingDeletionRequest,
    ) {}

    /**
     * Schedule an instructor for deletion on staff request.
     *
     * Nothing is anonymised now: the nightly `account:process-deletion-requests`
     * does that once the 30-day grace period ends. Until then the instructor is
     * `suspended` (out of public booking search), signed out everywhere and
     * blocked from signing in, and their pupils and lessons are left as they are
     * so a cancel restores everything. A pending self-service request is taken
     * over (keeping its earlier date) rather than duplicated. No email is sent.
     */
    public function __invoke(Instructor $instructor, User $requestedBy): AccountDeletionRequest
    {
        $instructor->loadMissing('user');
        $user = $instructor->user;

        return DB::transaction(function () use ($instructor, $user, $requestedBy): AccountDeletionRequest {
            $previousStatus = $instructor->status === InstructorStatus::Suspended->value
                ? null
                : $instructor->status;

            $deletionRequest = ($this->getPendingDeletionRequest)($user);

            if ($deletionRequest) {
                $deletionRequest->update([
                    'requested_by_user_id' => $requestedBy->id,
                    'previous_instructor_status' => $previousStatus ?? $deletionRequest->previous_instructor_status,
                ]);
            } else {
                $deletionRequest = AccountDeletionRequest::create([
                    'user_id' => $user->id,
                    'requested_by_user_id' => $requestedBy->id,
                    'status' => AccountDeletionRequestStatus::PENDING,
                    'reason' => 'Deleted by staff',
                    'previous_instructor_status' => $previousStatus,
                    'requested_at' => now(),
                    'scheduled_for' => now()->addDays(CreateAccountDeletionRequestAction::GRACE_PERIOD_DAYS),
                ]);
            }

            $instructor->forceFill(['status' => InstructorStatus::Suspended->value])->save();

            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $instructor->logActivity(
                'Instructor scheduled for deletion on '.$deletionRequest->scheduled_for->format('j M Y'),
                'instructor',
                [
                    'deletion_request_id' => $deletionRequest->id,
                    'requested_by_user_id' => $requestedBy->id,
                    'scheduled_for' => $deletionRequest->scheduled_for->toIso8601String(),
                    'previous_status' => $previousStatus,
                ],
            );

            return $deletionRequest;
        });
    }
}
