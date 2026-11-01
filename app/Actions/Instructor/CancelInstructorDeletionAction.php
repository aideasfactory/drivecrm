<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Enums\AccountDeletionRequestStatus;
use App\Enums\InstructorStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelInstructorDeletionAction
{
    /**
     * Cancel a staff-scheduled instructor deletion during the grace period.
     *
     * Restores the status the instructor had before it was scheduled, but only
     * if it is still `suspended` (a status changed by hand since is kept). No
     * email is sent: the instructor never asked for the deletion.
     */
    public function __invoke(Instructor $instructor, AccountDeletionRequest $deletionRequest, User $cancelledBy): void
    {
        DB::transaction(function () use ($instructor, $deletionRequest, $cancelledBy): void {
            $deletionRequest->update([
                'status' => AccountDeletionRequestStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);

            if ($instructor->status === InstructorStatus::Suspended->value) {
                $instructor->forceFill([
                    'status' => $deletionRequest->previous_instructor_status ?? InstructorStatus::Active->value,
                ])->save();
            }

            $instructor->logActivity(
                'Instructor deletion cancelled',
                'instructor',
                [
                    'deletion_request_id' => $deletionRequest->id,
                    'cancelled_by_user_id' => $cancelledBy->id,
                ],
            );
        });
    }
}
