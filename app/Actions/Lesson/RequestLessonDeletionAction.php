<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\LessonDeletionRequestStatus;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonDeletionRequest;
use App\Models\LessonPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RequestLessonDeletionAction
{
    public function __construct(
        protected GetLessonDeletionBlockerAction $getLessonDeletionBlocker,
    ) {}

    /**
     * Open a pending request for an owner to delete one of the instructor's
     * past lessons. The lesson stays as it is until an owner approves.
     * Snapshots the pupil, instructor and lesson time so the request still
     * reads correctly once the lesson is gone.
     *
     * @throws RuntimeException When the lesson isn't eligible or already has a pending request.
     */
    public function __invoke(Lesson $lesson, Instructor $instructor, User $requestedBy, string $reason): LessonDeletionRequest
    {
        return DB::transaction(function () use ($lesson, $instructor, $requestedBy, $reason): LessonDeletionRequest {
            $lockedLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->first();

            if ($lockedLesson && $lockedLesson->pendingDeletionRequest()->exists()) {
                throw new RuntimeException('A deletion request is already pending for this lesson.');
            }

            // Waits for an invoice being created right now, so the check below sees it.
            $lockedLesson?->setRelation('lessonPayment', LessonPayment::query()->where('lesson_id', $lockedLesson->id)->lockForUpdate()->first());

            $blocker = ($this->getLessonDeletionBlocker)($lockedLesson);

            if ($blocker !== null) {
                throw new RuntimeException($blocker);
            }

            $lockedLesson->loadMissing(['order.student', 'instructor.user']);
            $student = $lockedLesson->order?->student;

            return LessonDeletionRequest::create([
                'lesson_id' => $lockedLesson->id,
                'pending_lesson_id' => $lockedLesson->id,
                'instructor_id' => $instructor->id,
                'student_id' => $student?->id,
                'order_id' => $lockedLesson->order_id,
                'requested_by_user_id' => $requestedBy->id,
                'status' => LessonDeletionRequestStatus::PENDING,
                'reason' => trim($reason),
                'instructor_name' => $instructor->user?->name,
                'student_name' => $student ? trim($student->first_name.' '.$student->surname) : null,
                'lesson_date' => $lockedLesson->date?->format('Y-m-d'),
                'lesson_start_time' => $lockedLesson->start_time?->format('H:i'),
                'lesson_end_time' => $lockedLesson->end_time?->format('H:i'),
                'requested_at' => now(),
            ]);
        });
    }
}
