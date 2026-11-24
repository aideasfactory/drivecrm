<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonDeletionRequestStatus;
use App\Models\LessonDeletionRequest;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\Log;

class NotifyInstructorOfLessonDeletionDecisionAction
{
    public function __construct(
        protected PushNotificationService $pushNotificationService,
    ) {}

    /**
     * Push the instructor who asked that their lesson deletion request was
     * approved (lesson deleted) or rejected (lesson kept). No-ops without an
     * Expo push token; failures are logged and never thrown, so they can't
     * undo the decision. The pupil is not told.
     */
    public function __invoke(LessonDeletionRequest $deletionRequest): void
    {
        $approved = $deletionRequest->status === LessonDeletionRequestStatus::APPROVED;

        if (! $approved && $deletionRequest->status !== LessonDeletionRequestStatus::REJECTED) {
            return;
        }

        $user = $deletionRequest->instructor?->user;

        if (! $user?->isInstructor()) {
            return;
        }

        try {
            $pupilName = $deletionRequest->student_name ?: 'your pupil';
            $when = $deletionRequest->lesson_date?->format('D j M') ?? 'an unknown date';

            if ($deletionRequest->lessonStartTimeLabel()) {
                $when .= ' at '.$deletionRequest->lessonStartTimeLabel();
            }

            [$title, $body] = $approved
                ? ['Lesson deleted', "Your request was approved. The lesson with {$pupilName} on {$when} has been deleted."]
                : ['Deletion request rejected', "Your request to delete the lesson with {$pupilName} on {$when} was rejected. The lesson has been kept."];

            $this->pushNotificationService->queueIfHasToken($user, $title, $body, [
                'type' => $approved ? 'lesson_deletion_approved' : 'lesson_deletion_rejected',
                'lesson_deletion_request_id' => $deletionRequest->id,
                'lesson_id' => $approved ? ($deletionRequest->lesson_snapshot['id'] ?? null) : $deletionRequest->lesson_id,
                'student_id' => $deletionRequest->student_id,
                'lesson_date' => $deletionRequest->lesson_date?->format('Y-m-d'),
                'lesson_start_time' => $deletionRequest->lessonStartTimeLabel(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to notify instructor of a lesson deletion decision', [
                'lesson_deletion_request_id' => $deletionRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
