<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Lesson\ApproveLessonDeletionRequestAction;
use App\Actions\Lesson\CancelLessonDeletionRequestAction;
use App\Actions\Lesson\GetLessonDeletionBlockerAction;
use App\Actions\Lesson\GetLessonDeletionRequestsAction;
use App\Actions\Lesson\NotifyInstructorOfLessonDeletionDecisionAction;
use App\Actions\Lesson\RejectLessonDeletionRequestAction;
use App\Actions\Lesson\RequestLessonDeletionAction;
use App\Actions\Shared\LogActivityAction;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonDeletionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Instructors ask for a past lesson logged by mistake to be deleted; owners
 * (full access and the bookings team) approve, which permanently deletes the
 * lesson, or reject, which keeps it. Pending counts are not cached: they
 * change with every request and decision.
 */
class LessonDeletionRequestService extends BaseService
{
    public function __construct(
        protected GetLessonDeletionBlockerAction $getLessonDeletionBlocker,
        protected RequestLessonDeletionAction $requestLessonDeletion,
        protected CancelLessonDeletionRequestAction $cancelLessonDeletionRequest,
        protected ApproveLessonDeletionRequestAction $approveLessonDeletionRequest,
        protected RejectLessonDeletionRequestAction $rejectLessonDeletionRequest,
        protected GetLessonDeletionRequestsAction $getLessonDeletionRequests,
        protected NotifyInstructorOfLessonDeletionDecisionAction $notifyInstructorOfDecision,
        protected LogActivityAction $logActivity,
    ) {}

    /**
     * Why the lesson can't be deleted on request, or null when it can.
     */
    public function deletionBlocker(?Lesson $lesson): ?string
    {
        return ($this->getLessonDeletionBlocker)($lesson);
    }

    /**
     * @throws RuntimeException When the lesson isn't eligible or already has a pending request.
     */
    public function requestDeletion(Lesson $lesson, Instructor $instructor, User $requestedBy, string $reason): LessonDeletionRequest
    {
        $deletionRequest = ($this->requestLessonDeletion)($lesson, $instructor, $requestedBy, $reason);

        ($this->logActivity)(
            $instructor,
            "Asked Head Office to delete the lesson with {$this->pupilLabel($deletionRequest)} on {$this->lessonLabel($deletionRequest)}. Reason: {$deletionRequest->reason}",
            'lesson',
            $this->logMetadata($deletionRequest),
            'Requested lesson deletion',
        );

        return $deletionRequest;
    }

    /**
     * The lesson's pending deletion request, if any.
     */
    public function getPendingRequest(Lesson $lesson): ?LessonDeletionRequest
    {
        return $lesson->pendingDeletionRequest()->first();
    }

    /**
     * @throws RuntimeException When the request is no longer pending.
     */
    public function cancelRequest(LessonDeletionRequest $deletionRequest): LessonDeletionRequest
    {
        $cancelled = ($this->cancelLessonDeletionRequest)($deletionRequest);

        if ($cancelled->instructor) {
            ($this->logActivity)(
                $cancelled->instructor,
                "Withdrew the request to delete the lesson with {$this->pupilLabel($cancelled)} on {$this->lessonLabel($cancelled)}.",
                'lesson',
                $this->logMetadata($cancelled),
                'Withdrew lesson deletion request',
            );
        }

        return $cancelled;
    }

    /**
     * Approve the request and permanently delete the lesson, then push the
     * instructor. Logged on the instructor and the pupil with who approved.
     *
     * @throws RuntimeException When the request is no longer pending or the lesson can no longer be deleted.
     */
    public function approve(LessonDeletionRequest $deletionRequest, User $actor): LessonDeletionRequest
    {
        $approved = ($this->approveLessonDeletionRequest)($deletionRequest, $actor);

        $this->logDecision($approved, $actor, 'approved and the lesson was permanently deleted');
        ($this->notifyInstructorOfDecision)($approved);

        return $approved;
    }

    /**
     * Reject the request (the lesson is kept), then push the instructor.
     *
     * @throws RuntimeException When the request is no longer pending.
     */
    public function reject(LessonDeletionRequest $deletionRequest, User $actor): LessonDeletionRequest
    {
        $rejected = ($this->rejectLessonDeletionRequest)($deletionRequest, $actor);

        $this->logDecision($rejected, $actor, 'rejected and the lesson was kept');
        ($this->notifyInstructorOfDecision)($rejected);

        return $rejected;
    }

    /**
     * @return Collection<int, LessonDeletionRequest>
     */
    public function getPendingRequests(): Collection
    {
        return ($this->getLessonDeletionRequests)(true);
    }

    /**
     * @return Collection<int, LessonDeletionRequest>
     */
    public function getDecidedRequests(): Collection
    {
        return ($this->getLessonDeletionRequests)(false);
    }

    public function countPending(): int
    {
        return LessonDeletionRequest::query()->pending()->count();
    }

    /**
     * Rows for the admin list. Pending rows carry `blocked_reason` when the
     * lesson can no longer be deleted (paid, paid out or signed off since),
     * so the owner knows to reject instead.
     *
     * @return list<array<string, mixed>>
     */
    public function getAdminRows(bool $pending): array
    {
        $requests = $pending ? $this->getPendingRequests() : $this->getDecidedRequests();

        return $requests->map(fn (LessonDeletionRequest $deletionRequest): array => [
            'id' => $deletionRequest->id,
            'status' => $deletionRequest->status->value,
            'status_label' => $deletionRequest->status->label(),
            'lesson_id' => $deletionRequest->lesson_id ?? ($deletionRequest->lesson_snapshot['id'] ?? null),
            'lesson_exists' => $deletionRequest->lesson !== null,
            'instructor_id' => $deletionRequest->instructor_id,
            'instructor_name' => $deletionRequest->instructor_name,
            'student_id' => $deletionRequest->student_id,
            'student_name' => $deletionRequest->student_name,
            'lesson_date' => $deletionRequest->lesson_date?->format('Y-m-d'),
            'lesson_start_time' => $deletionRequest->lessonStartTimeLabel(),
            'lesson_end_time' => $deletionRequest->lessonEndTimeLabel(),
            'payment_mode' => $deletionRequest->lesson?->order?->payment_mode?->value
                ?? ($deletionRequest->lesson_snapshot['payment_mode'] ?? null),
            'reason' => $deletionRequest->reason,
            'requested_at' => $deletionRequest->requested_at?->toISOString(),
            'decided_at' => ($deletionRequest->decided_at ?? $deletionRequest->cancelled_at)?->toISOString(),
            'decided_by' => $deletionRequest->decidedBy?->name,
            'blocked_reason' => $deletionRequest->isPending() ? $this->deletionBlocker($deletionRequest->lesson) : null,
        ])->values()->all();
    }

    /**
     * The decision is already committed, so a logging failure must not turn
     * it into an error response.
     */
    protected function logDecision(LessonDeletionRequest $deletionRequest, User $actor, string $outcome): void
    {
        try {
            $this->writeDecisionLogs($deletionRequest, $actor, $outcome);
        } catch (Throwable $e) {
            Log::error('Failed to log a lesson deletion request decision', [
                'lesson_deletion_request_id' => $deletionRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function writeDecisionLogs(LessonDeletionRequest $deletionRequest, User $actor, string $outcome): void
    {
        $deletionRequest->loadMissing(['instructor', 'student']);
        $message = "Request to delete the lesson with {$this->pupilLabel($deletionRequest)} on {$this->lessonLabel($deletionRequest)} was {$outcome} by {$actor->name}. Reason given: {$deletionRequest->reason}";
        $metadata = [...$this->logMetadata($deletionRequest), 'decided_by_user_id' => $actor->id];

        if ($deletionRequest->instructor) {
            ($this->logActivity)($deletionRequest->instructor, $message, 'lesson', $metadata);
        }

        if ($deletionRequest->student) {
            ($this->logActivity)($deletionRequest->student, $message, 'lesson', $metadata);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function logMetadata(LessonDeletionRequest $deletionRequest): array
    {
        return [
            'lesson_deletion_request_id' => $deletionRequest->id,
            'lesson_id' => $deletionRequest->lesson_id ?? ($deletionRequest->lesson_snapshot['id'] ?? null),
            'instructor_id' => $deletionRequest->instructor_id,
            'student_id' => $deletionRequest->student_id,
            'status' => $deletionRequest->status->value,
        ];
    }

    protected function pupilLabel(LessonDeletionRequest $deletionRequest): string
    {
        return $deletionRequest->student_name ?: 'an unknown pupil';
    }

    protected function lessonLabel(LessonDeletionRequest $deletionRequest): string
    {
        $label = $deletionRequest->lesson_date?->format('D j M Y') ?? 'an unknown date';

        if ($deletionRequest->lessonStartTimeLabel()) {
            $label .= ' at '.$deletionRequest->lessonStartTimeLabel();
        }

        return $label;
    }
}
