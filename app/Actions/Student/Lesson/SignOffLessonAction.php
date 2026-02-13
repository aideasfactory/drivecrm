<?php

declare(strict_types=1);

namespace App\Actions\Student\Lesson;

use App\Enums\LessonStatus;
use App\Exceptions\InstructorNotOnboardedException;
use App\Exceptions\LessonAlreadyCompletedException;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\Payout;
use Exception;
use Illuminate\Support\Facades\DB;

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
     * Mirrors v1 LessonController@complete flow:
     * 1. Validate lesson is pending
     * 2. Validate instructor onboarding + payouts
     * 3. For weekly mode: verify payment received
     * 4. DB transaction:
     *    - Mark lesson completed
     *    - Update calendar item to completed
     *    - Create payout + Stripe transfer
     *    - Check order completion
     *
     * @return array{lesson: Lesson, payout: Payout, order_completed: bool}
     *
     * @throws LessonAlreadyCompletedException
     * @throws InstructorNotOnboardedException
     * @throws Exception
     */
    public function __invoke(Lesson $lesson, Instructor $instructor): array
    {
        // Guard: lesson must be pending
        if ($lesson->status !== LessonStatus::PENDING) {
            throw new LessonAlreadyCompletedException;
        }

        // Guard: instructor onboarding complete and payouts enabled
        if (! $instructor->onboarding_complete || ! $instructor->payouts_enabled) {
            throw new InstructorNotOnboardedException;
        }

        // Guard: for weekly payment mode, lesson payment must be received
        if ($lesson->order->isWeekly()) {
            if (! $lesson->lessonPayment) {
                throw new Exception('Lesson payment record not found.');
            }

            if (! $lesson->lessonPayment->isPaid()) {
                $dueDate = $lesson->lessonPayment->due_date?->format('d M Y') ?? 'unknown';
                throw new Exception("Cannot complete lesson. Weekly payment has not been received yet. Due date: {$dueDate}");
            }
        }

        return DB::transaction(function () use ($lesson, $instructor) {
            // 1. Mark lesson as completed
            $lesson = ($this->markLessonCompleted)($lesson);

            // 2. Update calendar item to completed
            ($this->updateCalendarItem)($lesson);

            // 3. Create payout + Stripe transfer
            $payout = ($this->createPayout)($lesson, $instructor);

            // 4. Check if all order lessons are completed
            $orderCompleted = ($this->checkOrderCompletion)($lesson->order);

            return [
                'lesson' => $lesson->fresh(),
                'payout' => $payout,
                'order_completed' => $orderCompleted,
            ];
        });
    }
}
