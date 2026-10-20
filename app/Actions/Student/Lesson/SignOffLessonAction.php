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
        protected CheckOrderCompletionAction $checkOrderCompletion,
        protected RecordCashLessonPaymentAction $recordCashLessonPayment
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
     * Imported orders (lessons brought in from another system) were settled
     * outside the platform: they skip the Stripe onboarding and payment guards
     * and never create a payout, so `payout` is null for them. Cash orders
     * (paid to the instructor at the lesson) skip the same guards and create
     * no payout; their lesson is recorded as paid in cash instead. A weekly
     * lesson paid by an alternative method (recorded before sign-off by
     * AlternativePaymentService::prepareSignOff) skips the onboarding guard and
     * creates no payout either: the pupil paid the instructor directly.
     *
     * @return array{lesson: Lesson, payout: Payout|null, order_completed: bool}
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

        $isImported = $lesson->order->isImported();
        $isCash = $lesson->order->isCash();
        $isAlternativePayment = $lesson->lessonPayment?->isPaidByAlternativeMethod() === true;

        // Guard: instructor onboarding complete and payouts enabled
        // payouts_enabled correlates with Stripe's 'transfers' capability — without it,
        // platform transfers to the connected account will fail. Imported, cash and
        // alternative-payment lessons never pay out through Stripe, so they don't need it.
        if (! $isImported && ! $isCash && ! $isAlternativePayment && (! $instructor->onboarding_complete || ! $instructor->payouts_enabled)) {
            throw new InstructorNotOnboardedException;
        }

        // Guard: lesson payment must be received before sign-off.
        // Imported and cash orders match neither branch — they are settled outside the platform.
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

        return DB::transaction(function () use ($lesson, $instructor, $isImported, $isCash, $isAlternativePayment) {
            // 1. Mark lesson as completed
            $lesson = ($this->markLessonCompleted)($lesson);

            // 2. Update calendar item to completed
            ($this->updateCalendarItem)($lesson);

            // 3. Create payout + Stripe transfer (never for imported or cash lessons).
            // A cash lesson is recorded as paid in cash instead: the instructor
            // already has the money.
            if ($isCash) {
                ($this->recordCashLessonPayment)($lesson);
            }

            $payout = $isImported || $isCash || $isAlternativePayment ? null : ($this->createPayout)($lesson, $instructor);

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
