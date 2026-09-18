<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonStatus;
use App\Exceptions\LessonAlreadyCompletedException;
use App\Exceptions\PayoutAlreadyProcessedException;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SignOffLessonAction
{
    public function __construct(
        protected MarkLessonCompletedAction $markLessonCompleted,
        protected UpdateCalendarItemCompletedAction $updateCalendarItem,
        protected CreateLessonPayoutAction $createPayout,
        protected CheckOrderCompletionAction $checkOrderCompletion
    ) {}

    /**
     * Orchestrate the full lesson sign-off process.
     *
     * Completion (status, calendar, order) is committed first. The Stripe
     * payout runs afterwards so a transfer failure cannot roll back the
     * signed-off lesson — the client can clear Needs Sign Off even when
     * payout retries later.
     *
     * Already-completed lessons are treated as idempotent so the background
     * job can finish payout + notifications after the API marked the lesson
     * complete in the request.
     *
     * @return array{lesson: Lesson, payout: Payout|null, order_completed: bool}
     *
     * @throws LessonAlreadyCompletedException
     * @throws Exception
     */
    public function __invoke(Lesson $lesson, Instructor $instructor): array
    {
        $lesson->loadMissing(['order.student', 'lessonPayment', 'payout', 'calendarItem']);

        if ($lesson->status === LessonStatus::PENDING) {
            $this->assertPaymentReceived($lesson);

            $completed = DB::transaction(function () use ($lesson) {
                $lesson = ($this->markLessonCompleted)($lesson);
                ($this->updateCalendarItem)($lesson);
                $orderCompleted = ($this->checkOrderCompletion)($lesson->order);

                return [
                    'lesson' => $lesson->fresh(['order', 'lessonPayment', 'payout', 'calendarItem']),
                    'order_completed' => $orderCompleted,
                ];
            });

            $lesson = $completed['lesson'];
            $orderCompleted = $completed['order_completed'];
        } elseif ($lesson->status === LessonStatus::COMPLETED) {
            $orderCompleted = ($this->checkOrderCompletion)($lesson->order);
        } else {
            throw new LessonAlreadyCompletedException;
        }

        $payout = $this->attemptPayout($lesson, $instructor);

        return [
            'lesson' => $lesson->fresh(['payout']),
            'payout' => $payout,
            'order_completed' => $orderCompleted,
        ];
    }

    /**
     * Mark the lesson completed without attempting a Stripe payout.
     *
     * Used by the mobile API so the request can return a confirmed
     * signed-off lesson immediately.
     *
     * @throws LessonAlreadyCompletedException
     * @throws Exception
     */
    public function completeWithoutPayout(Lesson $lesson): Lesson
    {
        $lesson->loadMissing(['order.student', 'lessonPayment', 'calendarItem']);

        if ($lesson->status !== LessonStatus::PENDING) {
            throw new LessonAlreadyCompletedException;
        }

        $this->assertPaymentReceived($lesson);

        return DB::transaction(function () use ($lesson) {
            $lesson = ($this->markLessonCompleted)($lesson);
            ($this->updateCalendarItem)($lesson);
            ($this->checkOrderCompletion)($lesson->order);

            return $lesson->fresh();
        });
    }

    /**
     * Payment must be received before the lesson can be marked complete.
     * Stripe onboarding is checked when creating the payout, not here —
     * otherwise a Connect delay would silently prevent sign-off from persisting.
     *
     * @throws Exception
     */
    public function assertPaymentReceived(Lesson $lesson): void
    {
        $lesson->loadMissing(['order', 'lessonPayment']);

        if ($lesson->order->isWeekly()) {
            if (! $lesson->lessonPayment) {
                throw new Exception('Lesson payment record not found.');
            }

            if (! $lesson->lessonPayment->isPaid()) {
                $dueDate = $lesson->lessonPayment->due_date?->format('d M Y') ?? 'unknown';
                throw new Exception("Cannot sign off lesson. Payment has not been received yet. Due date: {$dueDate}");
            }
        } elseif ($lesson->order->isUpfront()) {
            if (! $lesson->order->isActive()) {
                throw new Exception('Cannot sign off lesson. The order has not been paid for.');
            }
        }
    }

    protected function attemptPayout(Lesson $lesson, Instructor $instructor): ?Payout
    {
        try {
            return ($this->createPayout)($lesson, $instructor);
        } catch (PayoutAlreadyProcessedException) {
            return $lesson->fresh()?->payout;
        } catch (Exception $e) {
            Log::error('Lesson payout failed after sign-off', [
                'lesson_id' => $lesson->id,
                'instructor_id' => $instructor->id,
                'error' => $e->getMessage(),
            ]);

            return $lesson->fresh()?->payout;
        }
    }
}
