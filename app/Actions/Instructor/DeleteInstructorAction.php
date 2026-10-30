<?php

declare(strict_types=1);

namespace App\Actions\Instructor;

use App\Actions\Account\GetPendingAccountDeletionRequestAction;
use App\Actions\Account\ProcessAccountDeletionAction;
use App\Enums\AccountDeletionRequestStatus;
use App\Models\AccountDeletionRequest;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteInstructorAction
{
    public function __construct(
        protected GetPendingAccountDeletionRequestAction $getPendingDeletionRequest,
        protected ProcessAccountDeletionAction $processDeletion,
    ) {}

    /**
     * Delete an instructor immediately on staff request.
     *
     * Runs the same anonymise-and-archive process as a due self-service
     * deletion request (no grace period, no confirmation email). The
     * instructor row is never hard-deleted: orders, lessons and payouts
     * cascade on `instructors.id` and must be kept. An existing pending
     * self-service request is completed rather than duplicated.
     */
    public function __invoke(Instructor $instructor, ?User $deletedBy = null): void
    {
        $instructor->loadMissing('user');
        $user = $instructor->user;
        $profilePicturePath = $instructor->profile_picture_path;

        DB::transaction(function () use ($instructor, $user, $deletedBy): void {
            $instructor->logActivity(
                'Instructor profile deleted',
                'instructor',
                [
                    'deleted_by_user_id' => $deletedBy?->id,
                    'students_detached' => $instructor->students()->count(),
                ],
            );

            $deletionRequest = ($this->getPendingDeletionRequest)($user)
                ?? AccountDeletionRequest::create([
                    'user_id' => $user->id,
                    'status' => AccountDeletionRequestStatus::PENDING,
                    'reason' => 'Deleted by staff',
                    'requested_at' => now(),
                    'scheduled_for' => now(),
                ]);

            ($this->processDeletion)($deletionRequest);

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        if ($profilePicturePath) {
            Storage::disk('s3')->delete($profilePicturePath);
        }
    }
}
