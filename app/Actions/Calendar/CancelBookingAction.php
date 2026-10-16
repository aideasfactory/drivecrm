<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Shared\Note\CreateNoteAction;
use App\Actions\Student\Lesson\CreateLessonPayoutAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Actions\Student\RevokeTestPassGuaranteeAction;
use App\Enums\LessonCancellationOutcome;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Instructor;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\RefundRequiredNotification;
use App\Services\InstructorCalendarService;
use App\Services\OrderService;
use App\Services\StripeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Throwable;

class CancelBookingAction
{
    public function __construct(
        protected ReopenCancelledLessonSlotAction $reopenCancelledLessonSlot,
        protected CreateLessonPayoutAction $createLessonPayout,
        protected CreateNoteAction $createNote,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected RevokeTestPassGuaranteeAction $revokeTestPassGuarantee,
        protected StripeService $stripeService,
    ) {}

    /**
     * Cancel the booking attached to a calendar item. The lesson(s) are marked
     * cancelled (kept for history) and taken out of the diary, while their
     * slots go back to open availability so the instructor can offer them to
     * other pupils. Future weekly invoices stop automatically because the
     * invoice sender skips cancelled lessons.
     *
     * Each paid lesson is either refunded or paid out to the instructor:
     *  - more than 48 hours before it starts: refunded;
     *  - inside 48 hours: refunded when `$refundLesson` is ticked, otherwise
     *    paid out to the instructor (Stripe transfer, as at sign-off).
     * No Stripe refund happens here — Head Office is emailed to refund manually.
     * Every cancelled lesson is noted on the instructor's profile as
     * Cancelled/Refunded, Cancelled/Paid or Cancelled/Unpaid.
     *
     * A booking still awaiting its first payment is always cancelled as a
     * whole (its price covers every lesson), and its Stripe Checkout session
     * is closed first so it can no longer be paid. Open weekly invoices for
     * the cancelled lessons are voided. If Stripe cannot do either, nothing is
     * cancelled.
     *
     * @param  bool  $applyToFutureInOrder  When true, also cancel every future un-signed-off lesson in the same order. Ignored for a booking awaiting its first payment.
     * @param  bool  $refundLesson  The instructor's "Refund Lesson" choice for lessons inside the 48-hour window.
     * @return array{cancelled_count: int, refund_required_count: int, payout_count: int}
     */
    public function __invoke(
        CalendarItem $item,
        string $reason,
        bool $applyToFutureInOrder,
        User $actor,
        bool $refundLesson = false,
    ): array {
        $item->loadMissing(['calendar', 'lessons.order.student.user', 'lessons.lessonPayment', 'lessons.payout']);

        $anchorLesson = $item->lessons->first();

        if (! $anchorLesson) {
            throw new RuntimeException('Calendar item has no lesson — nothing to cancel.');
        }

        if ($this->isProtected($anchorLesson)) {
            throw new RuntimeException('This lesson is completed or already paid out and cannot be cancelled.');
        }

        $order = $anchorLesson->order;
        $instructor = $item->calendar->instructor;

        $isUnpaidBooking = $order?->isPending() === true && ! $order->isImported();

        $cancelSet = $isUnpaidBooking
            ? $this->buildWholeOrderCancelSet($order)
            : $this->buildCancelSet($anchorLesson, $applyToFutureInOrder);

        // Stripe first: nothing is cancelled unless the lessons can no longer be paid for.
        if ($isUnpaidBooking) {
            $this->closeUnpaidCheckout($order);
        }

        $voidedPayments = $this->stopOpenInvoices($cancelSet);

        try {
            return $this->cancelLessons($cancelSet, $order, $instructor, $reason, $actor, $refundLesson, $isUnpaidBooking);
        } catch (Throwable $e) {
            // The lessons stay booked, so their invoices must be issued again.
            $this->forgetVoidedInvoices($voidedPayments);

            throw $e;
        }
    }

    /**
     * Cancel the lessons once nothing can be paid for them any more.
     *
     * @param  Collection<int, Lesson>  $cancelSet
     * @return array{cancelled_count: int, refund_required_count: int, payout_count: int}
     */
    protected function cancelLessons(
        Collection $cancelSet,
        ?Order $order,
        ?Instructor $instructor,
        string $reason,
        User $actor,
        bool $refundLesson,
        bool $isUnpaidBooking,
    ): array {
        /** @var Collection<int, LessonCancellationOutcome> $outcomes */
        $outcomes = $cancelSet->mapWithKeys(fn (Lesson $lesson): array => [
            $lesson->id => $this->outcomeFor($lesson, $refundLesson),
        ]);

        $refundLessons = $cancelSet->filter(fn (Lesson $lesson): bool => $outcomes[$lesson->id] === LessonCancellationOutcome::Refunded)->values();
        $payoutLessons = $cancelSet->filter(fn (Lesson $lesson): bool => $outcomes[$lesson->id] === LessonCancellationOutcome::Paid)->values();
        $transferLessons = $payoutLessons->reject(fn (Lesson $lesson): bool => $lesson->order?->isImported() === true)->values();

        if ($transferLessons->isNotEmpty() && (! $instructor?->onboarding_complete || ! $instructor->payouts_enabled)) {
            throw new RuntimeException('The instructor cannot receive payouts yet, so this late cancellation cannot be paid out. Tick "Refund Lesson" to refund the pupil instead.');
        }

        $affectedDates = collect();

        DB::transaction(function () use ($cancelSet, $reason, $affectedDates, $transferLessons, $instructor, $isUnpaidBooking, $order): void {
            if ($isUnpaidBooking) {
                $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();

                if (! $lockedOrder?->isPending()) {
                    throw new RuntimeException('Payment for this booking has just come through. Refresh and cancel again to refund it.');
                }
            }

            foreach ($cancelSet as $lesson) {
                $lesson->status = LessonStatus::CANCELLED;
                $lesson->cancellation_reason = $reason;
                $lesson->cancelled_at = now();

                $calendarItem = $lesson->calendarItem;

                // Detach the lesson first so the slot can be reopened while the
                // cancelled lesson is retained for history.
                $lesson->calendar_item_id = null;
                $lesson->save();

                if ($calendarItem) {
                    $affectedDates->push($calendarItem->calendar?->date?->format('Y-m-d'));
                    ($this->reopenCancelledLessonSlot)($calendarItem);
                }
            }

            // Stripe transfers last: a failure rolls the cancellation back, and
            // nothing after them in the transaction can fail and orphan a transfer.
            foreach ($transferLessons as $lesson) {
                ($this->createLessonPayout)($lesson, $instructor);
            }
        });

        $orderCancelled = $this->cancelOrderIfFullyCancelled($order);

        $guaranteeRemoved = $this->removeRefundedGuarantee($order, $refundLessons, $orderCancelled);

        // Cancelled lessons leave a gap in the student's sequence — renumber the
        // remaining open lessons so numbers stay contiguous and chronological.
        if ($order?->student_id) {
            ($this->recalculateStudentLessonNumbers)($order->student_id);
        }

        $this->invalidateCalendarCache($instructor?->id, $affectedDates);

        $this->noteOutcomesOnInstructor($instructor, $cancelSet, $outcomes, $order, $reason);

        $this->sendNotifications($cancelSet, $refundLessons, $payoutLessons, $order, $reason, $orderCancelled, $actor, $guaranteeRemoved);

        return [
            'cancelled_count' => $cancelSet->count(),
            'refund_required_count' => $refundLessons->count(),
            'payout_count' => $payoutLessons->count(),
        ];
    }

    /**
     * Decide whether a cancelled lesson is refunded or paid out. Unpaid lessons
     * have nothing to refund or pay out. Paid lessons are refunded unless the
     * cancellation is inside the 48-hour window and the instructor did not
     * tick "Refund Lesson".
     */
    protected function outcomeFor(Lesson $lesson, bool $refundLesson): LessonCancellationOutcome
    {
        if (! $this->wasPaid($lesson)) {
            return LessonCancellationOutcome::Unpaid;
        }

        if ($lesson->isLateCancellation() && ! $refundLesson) {
            return LessonCancellationOutcome::Paid;
        }

        return LessonCancellationOutcome::Refunded;
    }

    /**
     * Record each cancelled lesson on the instructor's profile as a note
     * starting Cancelled/Refunded, Cancelled/Paid or Cancelled/Unpaid.
     *
     * @param  Collection<int, Lesson>  $cancelSet
     * @param  Collection<int, LessonCancellationOutcome>  $outcomes
     */
    protected function noteOutcomesOnInstructor(
        ?Instructor $instructor,
        Collection $cancelSet,
        Collection $outcomes,
        ?Order $order,
        string $reason,
    ): void {
        if (! $instructor) {
            return;
        }

        $student = $order?->student;
        $studentName = $student ? trim($student->first_name.' '.$student->surname) : 'Unknown pupil';

        foreach ($cancelSet as $lesson) {
            $date = $lesson->date?->format('D j M Y') ?? 'unknown date';
            $time = ($lesson->start_time && $lesson->end_time)
                ? ' '.$lesson->start_time->format('H:i').'–'.$lesson->end_time->format('H:i')
                : '';

            ($this->createNote)(
                $instructor,
                "{$outcomes[$lesson->id]->noteLabel()} — {$studentName}, {$date}{$time} (booking #{$order?->id}). Reason: {$reason}",
            );
        }
    }

    /**
     * Build the set of lessons to cancel: the anchor plus, optionally, every
     * future un-signed-off sibling in the same order (same filter the bulk
     * reschedule uses).
     *
     * @return Collection<int, Lesson>
     */
    protected function buildCancelSet(Lesson $anchorLesson, bool $applyToFutureInOrder): Collection
    {
        $set = new Collection([$anchorLesson]);

        if (! $applyToFutureInOrder || ! $anchorLesson->order_id) {
            return $set;
        }

        $futureSiblings = Lesson::query()
            ->where('order_id', $anchorLesson->order_id)
            ->where('id', '!=', $anchorLesson->id)
            ->where('date', '>', $anchorLesson->date->format('Y-m-d'))
            ->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
            ->whereDoesntHave('payout')
            ->with(['calendarItem.calendar', 'lessonPayment', 'order'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $set->merge($futureSiblings);
    }

    /**
     * Every open lesson in an order, for cancelling a booking as a whole.
     *
     * @return Collection<int, Lesson>
     */
    protected function buildWholeOrderCancelSet(Order $order): Collection
    {
        return Lesson::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', [LessonStatus::COMPLETED, LessonStatus::CANCELLED])
            ->whereDoesntHave('payout')
            ->with(['calendarItem.calendar', 'lessonPayment', 'order'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Close the Stripe Checkout session of a booking awaiting its first
     * payment. Refuses the cancellation when the payment has just landed (the
     * booking is confirmed instead) or Stripe cannot close the session.
     */
    protected function closeUnpaidCheckout(Order $order): void
    {
        // Resolved here: OrderService depends on InstructorService, which depends on this action.
        $result = app(OrderService::class)->closeCheckoutForCancellation($order);

        if ($result === 'paid') {
            throw new RuntimeException('Payment for this booking has just come through. Refresh and cancel again to refund it.');
        }

        if ($result === 'failed') {
            throw new RuntimeException("We couldn't close the payment page for this booking. Please try again in a minute.");
        }
    }

    /**
     * Void the open Stripe invoice of every unpaid lesson being cancelled so it
     * can no longer be paid. An invoice found already paid (its webhook not yet
     * arrived) is recorded as paid here, so the lesson is refunded or paid out
     * like any other paid lesson. If an invoice cannot be voided, the invoices
     * voided so far are forgotten and the cancellation is refused.
     *
     * @param  Collection<int, Lesson>  $cancelSet
     * @return Collection<int, LessonPayment> The payments whose invoices were voided.
     */
    protected function stopOpenInvoices(Collection $cancelSet): Collection
    {
        $voidedPayments = collect();

        foreach ($cancelSet as $lesson) {
            $payment = $lesson->lessonPayment;

            if (! $payment || $payment->status !== PaymentStatus::DUE || ! $payment->stripe_invoice_id) {
                continue;
            }

            $result = $this->stripeService->voidInvoice($payment->stripe_invoice_id);

            if ($result['status'] === 'voided') {
                $voidedPayments->push($payment);

                continue;
            }

            if ($result['status'] === 'paid') {
                $payment->update([
                    'status' => PaymentStatus::PAID,
                    'stripe_charge_id' => $result['charge_id'] ?? $payment->stripe_charge_id,
                    'paid_at' => now(),
                ]);

                Log::info('Invoice found paid while cancelling its lesson — treating the lesson as paid', [
                    'lesson_id' => $lesson->id,
                    'lesson_payment_id' => $payment->id,
                    'stripe_invoice_id' => $payment->stripe_invoice_id,
                ]);

                continue;
            }

            $this->forgetVoidedInvoices($voidedPayments);

            throw new RuntimeException("We couldn't cancel the payment request for the lesson on {$lesson->date?->format('j M Y')}. Please try again in a minute.");
        }

        return $voidedPayments;
    }

    /**
     * Drop the voided invoice ids of lessons that were not cancelled after
     * all, so the invoice sender issues them fresh invoices.
     *
     * @param  Collection<int, LessonPayment>  $voidedPayments
     */
    protected function forgetVoidedInvoices(Collection $voidedPayments): void
    {
        $voidedPayments
            ->reject(fn (LessonPayment $payment): bool => $payment->lesson()->where('status', LessonStatus::CANCELLED)->exists())
            ->each(fn (LessonPayment $payment) => $payment->update(['stripe_invoice_id' => null]));
    }

    /**
     * A lesson is protected from cancellation once it is completed or has a
     * payout (already signed-off / the instructor has been compensated).
     */
    protected function isProtected(Lesson $lesson): bool
    {
        return $lesson->status === LessonStatus::COMPLETED || $lesson->payout !== null;
    }

    /**
     * Whether a lesson had been paid for — an upfront order that has been
     * confirmed (lesson is no longer a draft), or a weekly lesson whose
     * per-lesson payment is marked paid.
     */
    protected function wasPaid(Lesson $lesson): bool
    {
        if ($lesson->lessonPayment) {
            return $lesson->lessonPayment->isPaid();
        }

        return $lesson->order?->isUpfront() === true && ! $lesson->isDraft();
    }

    /**
     * If every non-completed lesson in the order is now cancelled, mark the
     * order itself cancelled.
     */
    protected function cancelOrderIfFullyCancelled(?Order $order): bool
    {
        if (! $order) {
            return false;
        }

        $hasOpenLessons = Lesson::query()
            ->where('order_id', $order->id)
            ->whereNotIn('status', [LessonStatus::CANCELLED, LessonStatus::COMPLETED])
            ->exists();

        if ($hasOpenLessons || $order->isClosed()) {
            return false;
        }

        $order->status = OrderStatus::CANCELLED;
        $order->save();

        return true;
    }

    /**
     * @param  Collection<int, string|null>  $affectedDates
     */
    protected function invalidateCalendarCache(?int $instructorId, Collection $affectedDates): void
    {
        if (! $instructorId) {
            return;
        }

        $calendarService = app(InstructorCalendarService::class);

        foreach ($affectedDates->filter()->unique() as $date) {
            $calendarService->invalidateCalendarCache($instructorId, $date);
        }
    }

    /**
     * A cancellation refunds the Pass Your Test Guarantee, so the pupil loses it
     * when a refunded lesson carried the guarantee charge, or when the
     * whole order is now cancelled (this also covers a guarantee included free).
     *
     * @param  Collection<int, Lesson>  $refundLessons
     */
    protected function removeRefundedGuarantee(?Order $order, Collection $refundLessons, bool $orderCancelled): bool
    {
        if (! $order?->includes_test_pass_guarantee) {
            return false;
        }

        $refundsGuaranteeCharge = $refundLessons->contains(
            fn (Lesson $lesson): bool => $lesson->paymentBreakdown()['test_pass_guarantee_pence'] > 0
        );

        if (! $orderCancelled && ! $refundsGuaranteeCharge) {
            return false;
        }

        return ($this->revokeTestPassGuarantee)($order);
    }

    /**
     * Always email the student. Email Head Office only when a lesson is being
     * refunded (a manual refund is required).
     *
     * @param  Collection<int, Lesson>  $cancelSet
     * @param  Collection<int, Lesson>  $refundLessons
     * @param  Collection<int, Lesson>  $payoutLessons
     */
    protected function sendNotifications(
        Collection $cancelSet,
        Collection $refundLessons,
        Collection $payoutLessons,
        ?Order $order,
        string $reason,
        bool $orderCancelled,
        User $actor,
        bool $guaranteeRemoved = false,
    ): void {
        $student = $order?->student;
        $instructor = $order?->instructor;
        $refundRequired = $refundLessons->isNotEmpty();

        if ($student?->user) {
            $student->user->notify(new BookingCancelledNotification(
                $student,
                $instructor,
                $cancelSet,
                $reason,
                $refundRequired,
                $payoutLessons->isNotEmpty(),
            ));

            $student->logActivity(
                "Cancelled {$cancelSet->count()} lesson(s) in booking #{$order?->id}: {$reason}",
                'notification',
                [
                    'order_id' => $order?->id,
                    'cancelled_lesson_ids' => $cancelSet->pluck('id')->all(),
                    'refund_required' => $refundRequired,
                    'refunded_lesson_ids' => $refundLessons->pluck('id')->all(),
                    'paid_out_lesson_ids' => $payoutLessons->pluck('id')->all(),
                    'order_cancelled' => $orderCancelled,
                    'cancelled_by_user_id' => $actor->id,
                ],
                "Cancelled {$cancelSet->count()} lesson(s): {$reason}",
            );
        }

        if ($refundRequired && config('mail.head_office_address')) {
            Notification::route('mail', config('mail.head_office_address'))
                ->notify(new RefundRequiredNotification(
                    $student,
                    $instructor,
                    $order,
                    $refundLessons,
                    $reason,
                    $guaranteeRemoved,
                ));
        }
    }
}
