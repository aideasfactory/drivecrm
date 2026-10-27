<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Calendar\ReopenCancelledLessonSlotAction;
use App\Actions\Shared\Note\CreateNoteAction;
use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Enums\LessonCancellationReason;
use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\Order;
use App\Models\User;
use App\Notifications\LessonsBankedNotification;
use App\Services\InstructorCalendarService;
use App\Services\StripeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BankLessonsAction
{
    public function __construct(
        protected ReopenCancelledLessonSlotAction $reopenLessonSlot,
        protected CreateNoteAction $createNote,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
        protected StripeService $stripeService,
    ) {}

    /**
     * Take the lesson on a diary item (and optionally every later open lesson
     * in the same booking) out of the diary without cancelling it, so the
     * pupil keeps it in their booking at the price they booked. The lesson
     * stays `pending` with its price and payment untouched; its date, times
     * and diary slot are cleared and `banked_at` is set. The slot goes back to
     * open availability, as it does on a cancel.
     *
     * With no date, a banked lesson is never invoiced, reminded or voided as
     * overdue, and it can't be signed off. An invoice already sent for it is
     * voided first (Stripe before anything changes); one found paid is
     * recorded as paid and the lesson is banked as paid. If an invoice can't
     * be voided, nothing is banked.
     *
     * Only lessons of a confirmed (active) booking can be banked: an unpaid
     * booking is still cancelled as a whole.
     *
     * @return array{banked_count: int, lessons: Collection<int, Lesson>}
     *
     * @throws RuntimeException When the lesson can't be banked
     */
    public function __invoke(
        CalendarItem $item,
        LessonCancellationReason $reasonCode,
        ?string $details,
        bool $applyToFutureInOrder,
        User $actor,
    ): array {
        $item->loadMissing(['calendar', 'lessons.order.student.user', 'lessons.order.instructor.user', 'lessons.lessonPayment', 'lessons.payout']);

        $anchorLesson = $item->lessons->first();

        if (! $anchorLesson) {
            throw new RuntimeException('Calendar item has no lesson — nothing to rebook later.');
        }

        $order = $anchorLesson->order;

        $this->ensureCanBank($anchorLesson, $order);

        $bankSet = $this->buildBankSet($anchorLesson, $applyToFutureInOrder);

        // Described now, while the lessons still have their dates.
        $originalSlots = $bankSet->mapWithKeys(fn (Lesson $lesson): array => [$lesson->id => $this->describeSlot($lesson)])->all();

        $details = trim((string) $details) === '' ? null : trim((string) $details);
        $reasonText = $reasonCode->describe($details);

        // Stripe first: an invoice that can still be paid would charge for a lesson with no date.
        $voidedPayments = $this->stopOpenInvoices($bankSet);

        $affectedDates = collect();

        try {
            DB::transaction(function () use ($bankSet, $reasonText, $actor, $affectedDates): void {
                foreach ($bankSet as $lesson) {
                    $wasProvisional = $lesson->isProvisional();
                    $calendarItem = $lesson->calendarItem;

                    $lesson->date = null;
                    $lesson->start_time = null;
                    $lesson->end_time = null;
                    $lesson->calendar_item_id = null;
                    $lesson->provisional_at = null;
                    $lesson->banked_at = now();
                    $lesson->banked_reason = $reasonText;
                    $lesson->banked_by_user_id = $actor->id;
                    $lesson->save();

                    // Still owed: invoiced afresh once the lesson has a date again.
                    LessonPayment::query()
                        ->where('lesson_id', $lesson->id)
                        ->where('status', PaymentStatus::DUE)
                        ->update(['stripe_invoice_id' => null, 'invoice_cancelled_at' => null, 'due_date' => null]);

                    if ($calendarItem) {
                        $affectedDates->push($calendarItem->calendar?->date?->format('Y-m-d'));

                        // A provisional lesson's own slot sits on top of whatever it
                        // clashed with, so it is removed rather than offered as free time.
                        if ($wasProvisional && $calendarItem->created_by_hold === true) {
                            $calendarItem->travelItem?->delete();
                            $calendarItem->delete();
                        } else {
                            ($this->reopenLessonSlot)($calendarItem);
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            // The lessons stay booked, so their invoices must be issued again.
            $voidedPayments->each(fn (LessonPayment $payment) => $payment->update(['stripe_invoice_id' => null]));

            throw $e;
        }

        if ($order?->student_id) {
            ($this->recalculateStudentLessonNumbers)($order->student_id);
        }

        $this->invalidateCalendarCache($item->calendar?->instructor_id, $affectedDates);

        $this->recordAndNotify($bankSet, $order, $originalSlots, $reasonText, $reasonCode, $actor);

        Log::info('Lessons banked to rebook later', [
            'order_id' => $order?->id,
            'lesson_ids' => $bankSet->pluck('id')->all(),
            'voided_lesson_payment_ids' => $voidedPayments->pluck('id')->all(),
            'banked_by_user_id' => $actor->id,
        ]);

        return [
            'banked_count' => $bankSet->count(),
            'lessons' => $bankSet,
        ];
    }

    /**
     * @throws RuntimeException
     */
    protected function ensureCanBank(Lesson $lesson, ?Order $order): void
    {
        if ($lesson->status === LessonStatus::COMPLETED || $lesson->payout !== null) {
            throw new RuntimeException('This lesson is completed or already paid out and cannot be taken out of the diary.');
        }

        if (! $lesson->canBeBanked()) {
            throw new RuntimeException("This booking hasn't been paid yet, so its lessons can't be kept to rebook later. Move it or cancel it instead.");
        }
    }

    /**
     * The anchor plus, optionally, every later open lesson in the same booking
     * (the same filter cancel and bulk reschedule use).
     *
     * @return Collection<int, Lesson>
     */
    protected function buildBankSet(Lesson $anchorLesson, bool $applyToFutureInOrder): Collection
    {
        $set = new Collection([$anchorLesson]);

        if (! $applyToFutureInOrder || ! $anchorLesson->order_id || ! $anchorLesson->date) {
            return $set;
        }

        $futureSiblings = Lesson::query()
            ->where('order_id', $anchorLesson->order_id)
            ->where('id', '!=', $anchorLesson->id)
            ->where('date', '>', $anchorLesson->date->format('Y-m-d'))
            ->where('status', LessonStatus::PENDING)
            ->whereDoesntHave('payout')
            ->with(['calendarItem.calendar', 'calendarItem.travelItem', 'lessonPayment', 'order'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $set->merge($futureSiblings);
    }

    /**
     * Void the open Stripe invoice of every unpaid lesson being banked. An
     * invoice found already paid (its webhook not yet arrived) is recorded as
     * paid. If one can't be voided, the invoices voided so far are forgotten
     * (so those lessons are invoiced again) and nothing is banked.
     *
     * @param  Collection<int, Lesson>  $bankSet
     * @return Collection<int, LessonPayment> The payments whose invoices were voided.
     */
    protected function stopOpenInvoices(Collection $bankSet): Collection
    {
        $voidedPayments = collect();

        foreach ($bankSet as $lesson) {
            $payment = $lesson->lessonPayment;

            if (! $payment || $payment->status !== PaymentStatus::DUE || ! $payment->stripe_invoice_id || $payment->invoice_cancelled_at !== null) {
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

                Log::info('Invoice found paid while banking its lesson — treating the lesson as paid', [
                    'lesson_id' => $lesson->id,
                    'lesson_payment_id' => $payment->id,
                    'stripe_invoice_id' => $payment->stripe_invoice_id,
                ]);

                continue;
            }

            $voidedPayments->each(fn (LessonPayment $voided) => $voided->update(['stripe_invoice_id' => null]));

            throw new RuntimeException("We couldn't cancel the payment request for the lesson on {$lesson->date?->format('j M Y')}. Please try again in a minute.");
        }

        return $voidedPayments;
    }

    /**
     * Note each banked lesson on the instructor's profile, log it on the
     * student and email them.
     *
     * @param  Collection<int, Lesson>  $bankSet
     * @param  array<int, string>  $originalSlots
     */
    protected function recordAndNotify(
        Collection $bankSet,
        ?Order $order,
        array $originalSlots,
        string $reasonText,
        LessonCancellationReason $reasonCode,
        User $actor,
    ): void {
        $student = $order?->student;
        $instructor = $order?->instructor;
        $studentName = $student ? trim($student->first_name.' '.$student->surname) : 'Unknown pupil';

        if ($instructor) {
            foreach ($bankSet as $lesson) {
                ($this->createNote)(
                    $instructor,
                    "Banked/Rebook later — {$studentName}, {$originalSlots[$lesson->id]} (booking #{$order?->id}). Reason: {$reasonText}",
                );
            }
        }

        if (! $student) {
            return;
        }

        $student->logActivity(
            "Kept {$bankSet->count()} lesson(s) in booking #{$order?->id} to rebook later: {$reasonText}",
            'booking',
            [
                'order_id' => $order?->id,
                'banked_lesson_ids' => $bankSet->pluck('id')->all(),
                'original_slots' => array_values($originalSlots),
                'reason_code' => $reasonCode->value,
                'banked_by_user_id' => $actor->id,
            ],
            "Kept {$bankSet->count()} lesson(s) to rebook later: {$reasonText}",
        );

        $student->user?->notify(new LessonsBankedNotification(
            $student,
            $instructor,
            array_values($originalSlots),
            $order?->package_name ?? 'lesson',
            $reasonText,
        ));
    }

    protected function describeSlot(Lesson $lesson): string
    {
        $date = $lesson->date?->format('l, j F Y') ?? 'Date to be confirmed';

        if ($lesson->start_time && $lesson->end_time) {
            return $date.' at '.$lesson->start_time->format('H:i').' - '.$lesson->end_time->format('H:i');
        }

        return $date;
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
}
