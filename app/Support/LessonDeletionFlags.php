<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Lesson\GetLessonDeletionBlockerAction;
use App\Models\Lesson;
use App\Models\LessonDeletionRequest;
use App\Models\User;

/**
 * The instructor-facing deletion request state of a lesson, shared by every
 * lesson payload the instructor app reads. Pupils always get the empty state.
 * Needs `pendingDeletionRequest`, `order`, `lessonPayment` (with its invoice
 * columns), `payout` and `instructorFinances` loaded to avoid extra queries.
 */
final class LessonDeletionFlags
{
    /**
     * @return array{deletion_pending: bool, deletion_request: array{id: int, status: string, reason: string, requested_at: string|null}|null, can_request_deletion: bool, deletion_blocked_reason: string|null}
     */
    public static function forLesson(?Lesson $lesson, ?User $viewer): array
    {
        if (! $lesson || $viewer?->isStudent() !== false) {
            return self::build(null, null, null, $viewer);
        }

        $pending = $lesson->pendingDeletionRequest;

        return self::build(
            self::requestPayload($pending),
            $pending === null ? app(GetLessonDeletionBlockerAction::class)($lesson) : null,
            $lesson->instructor_id,
            $viewer,
        );
    }

    /**
     * The pending request as it appears on lesson payloads, or null.
     *
     * @return array{id: int, status: string, reason: string, requested_at: string|null}|null
     */
    public static function requestPayload(?LessonDeletionRequest $pending): ?array
    {
        return $pending ? [
            'id' => $pending->id,
            'status' => $pending->status->value,
            'reason' => $pending->reason,
            'requested_at' => $pending->requested_at?->toISOString(),
        ] : null;
    }

    /**
     * @param  array{id: int, status: string, reason: string, requested_at: string|null}|null  $pendingRequest
     * @param  string|null  $blocker  Why the lesson can't be deleted on request (GetLessonDeletionBlockerAction).
     * @return array{deletion_pending: bool, deletion_request: array{id: int, status: string, reason: string, requested_at: string|null}|null, can_request_deletion: bool, deletion_blocked_reason: string|null}
     */
    public static function build(?array $pendingRequest, ?string $blocker, ?int $lessonInstructorId, ?User $viewer): array
    {
        if ($viewer?->isStudent() !== false || $lessonInstructorId === null) {
            return [
                'deletion_pending' => false,
                'deletion_request' => null,
                'can_request_deletion' => false,
                'deletion_blocked_reason' => null,
            ];
        }

        return [
            'deletion_pending' => $pendingRequest !== null,
            'deletion_request' => $pendingRequest,
            'can_request_deletion' => $pendingRequest === null
                && $blocker === null
                && $viewer->isInstructor()
                && $lessonInstructorId === $viewer->instructor?->id,
            'deletion_blocked_reason' => $pendingRequest === null ? $blocker : null,
        ];
    }
}
