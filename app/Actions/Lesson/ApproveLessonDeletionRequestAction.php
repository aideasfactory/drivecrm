<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\LessonDeletionRequestStatus;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\Lesson;
use App\Models\LessonDeletionRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ApproveLessonDeletionRequestAction
{
    public function __construct(
        protected GetLessonDeletionBlockerAction $getLessonDeletionBlocker,
        protected PermanentlyDeleteLessonAction $permanentlyDeleteLesson,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
    ) {}

    /**
     * Approve a pending request and permanently delete its lesson. The lesson
     * is re-checked under a lock first: if it has been paid, paid out or
     * signed off since the request, nothing changes and the reason is thrown.
     * The request keeps a snapshot of the deleted lesson and who approved it.
     *
     * Afterwards the pupil's open lessons are renumbered, a booking with no
     * open lessons left is closed (completed when it has signed-off lessons,
     * otherwise cancelled) and the instructor's diary cache is cleared.
     *
     * @throws RuntimeException When the request is no longer pending or the lesson can't be deleted.
     */
    public function __invoke(LessonDeletionRequest $deletionRequest, User $actor): LessonDeletionRequest
    {
        $outcome = DB::transaction(function () use ($deletionRequest, $actor): array {
            $locked = LessonDeletionRequest::query()->whereKey($deletionRequest->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('This deletion request has already been '.strtolower($locked->status->label()).'.');
            }

            $lesson = $locked->lesson_id
                ? Lesson::query()->whereKey($locked->lesson_id)->lockForUpdate()->first()
                : null;

            $lesson?->load(['order', 'lessonPayment', 'payout', 'instructorFinances', 'calendarItem.calendar', 'reflectiveLog']);

            $blocker = ($this->getLessonDeletionBlocker)($lesson);

            if ($blocker !== null) {
                throw new RuntimeException($blocker.' Reject the request instead.');
            }

            $snapshot = [
                ...$lesson->only([
                    'id', 'order_id', 'instructor_id', 'calendar_item_id', 'amount_pence', 'status',
                    'student_lesson_number', 'mileage', 'summary', 'is_practical_test', 'provisional_at',
                ]),
                'date' => $lesson->date?->format('Y-m-d'),
                'start_time' => $lesson->start_time?->format('H:i'),
                'end_time' => $lesson->end_time?->format('H:i'),
                'payment_mode' => $lesson->order?->payment_mode?->value,
                'lesson_payment' => $lesson->lessonPayment?->only(['id', 'amount_pence', 'status', 'due_date', 'stripe_invoice_id', 'invoice_cancelled_at']),
                'had_reflective_log' => $lesson->reflectiveLog !== null,
                'created_at' => $lesson->created_at?->toISOString(),
            ];

            $locked->update([
                'status' => LessonDeletionRequestStatus::APPROVED,
                'pending_lesson_id' => null,
                'lesson_snapshot' => $snapshot,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            $deleted = ($this->permanentlyDeleteLesson)($lesson);

            $locked->lesson_id = null;

            return [
                'request' => $locked,
                'order' => $lesson->order,
                'instructor_id' => $lesson->instructor_id,
                'calendar_date' => $deleted['calendar_date'],
                'snapshot' => $snapshot,
            ];
        });

        /** @var LessonDeletionRequest $approved */
        $approved = $outcome['request'];

        Log::info('Lesson permanently deleted on an approved instructor deletion request', [
            'lesson_deletion_request_id' => $approved->id,
            'lesson_id' => $outcome['snapshot']['id'],
            'instructor_id' => $approved->instructor_id,
            'student_id' => $approved->student_id,
            'approved_by_user_id' => $actor->id,
            'lesson' => $outcome['snapshot'],
        ]);

        $this->closeOrderIfNothingLeft($outcome['order']);

        if ($approved->student_id) {
            ($this->recalculateStudentLessonNumbers)($approved->student_id);
        }

        if ($outcome['instructor_id'] && $outcome['calendar_date']) {
            app(InstructorCalendarService::class)->invalidateCalendarCache($outcome['instructor_id'], $outcome['calendar_date']);
        }

        return $approved;
    }

    /**
     * Close an active booking once deleting the lesson leaves it with nothing
     * open: completed when some lessons were signed off, otherwise cancelled.
     */
    protected function closeOrderIfNothingLeft(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $order->refresh();

        if (! $order->isActive()) {
            return;
        }

        $hasOpenLessons = $order->lessons()
            ->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
            ->exists();

        if ($hasOpenLessons) {
            return;
        }

        $order->status = $order->lessons()->where('status', LessonStatus::COMPLETED)->exists()
            ? OrderStatus::COMPLETED
            : OrderStatus::CANCELLED;
        $order->save();
    }
}
