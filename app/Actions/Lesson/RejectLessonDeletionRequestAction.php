<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonDeletionRequestStatus;
use App\Models\LessonDeletionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RejectLessonDeletionRequestAction
{
    /**
     * Reject a pending request: the lesson is kept exactly as it is and stops
     * showing as "Deletion pending".
     *
     * @throws RuntimeException When the request is no longer pending.
     */
    public function __invoke(LessonDeletionRequest $deletionRequest, User $actor): LessonDeletionRequest
    {
        return DB::transaction(function () use ($deletionRequest, $actor): LessonDeletionRequest {
            $locked = LessonDeletionRequest::query()->whereKey($deletionRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('This deletion request has already been '.strtolower($locked->status->label()).'.');
            }

            $locked->update([
                'status' => LessonDeletionRequestStatus::REJECTED,
                'pending_lesson_id' => null,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            return $locked;
        });
    }
}
