<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonDeletionRequestStatus;
use App\Models\LessonDeletionRequest;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CancelLessonDeletionRequestAction
{
    /**
     * Withdraw the instructor's pending deletion request. The lesson is
     * untouched and a new request can be made later.
     *
     * @throws RuntimeException When an owner has already decided the request.
     */
    public function __invoke(LessonDeletionRequest $deletionRequest): LessonDeletionRequest
    {
        return DB::transaction(function () use ($deletionRequest): LessonDeletionRequest {
            $locked = LessonDeletionRequest::query()->whereKey($deletionRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('This deletion request has already been '.strtolower($locked->status->label()).'.');
            }

            $locked->update([
                'status' => LessonDeletionRequestStatus::CANCELLED,
                'pending_lesson_id' => null,
                'cancelled_at' => now(),
            ]);

            return $locked;
        });
    }
}
