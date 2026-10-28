<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\CalendarItemStatus;
use App\Enums\LessonStatus;
use App\Enums\SlotOfferStatus;
use App\Models\Lesson;
use App\Support\AlternativePayments;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class CalendarItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lesson = $this->bookingLesson();

        return [
            'id' => $this->id,
            'date' => $this->whenLoaded('calendar', fn () => $this->calendar->date->format('Y-m-d')),
            'start_time' => Carbon::parse($this->start_time)->format('H:i'),
            'end_time' => Carbon::parse($this->end_time)->format('H:i'),
            'is_available' => $this->is_available,
            'status' => $this->status?->value,
            'item_type' => $this->item_type?->value ?? 'slot',
            'travel_time_minutes' => $this->travel_time_minutes,
            'parent_item_id' => $this->parent_item_id,
            'notes' => $this->notes,
            'unavailability_reason' => $this->unavailability_reason,
            'recurrence_pattern' => $this->recurrence_pattern?->value ?? 'none',
            'recurrence_end_date' => $this->recurrence_end_date?->format('Y-m-d'),
            'recurrence_group_id' => $this->recurrence_group_id,

            // Booking context (populated for booked/completed items when the lesson
            // relations are loaded — e.g. the calendar index endpoint). Drives the
            // status-dependent edit UI in the app: `future_siblings_count` powers the
            // "move just this / the whole booking" prompt; `is_paid` locks a paid
            // lesson down to reschedule-only.
            'lesson_id' => $lesson?->id,
            'order_id' => $lesson?->order_id,
            // The pupil's student ID, for pupil-scoped calls from the diary
            // (e.g. POST /students/{student}/orders/{order}/alternative-payment).
            'student_id' => $lesson?->order?->student_id,
            'student_name' => $this->studentName($lesson),
            'is_paid' => $this->isPaid($lesson),
            // A package week booked onto a time already taken: shown stacked next
            // to what it clashes with while the DRIVE team rearranges it.
            'is_provisional' => $lesson?->isProvisional() === true,
            // Who rearranges it: `instructor` (they booked the clash) or `drive_team`.
            'provisional_handled_by' => $lesson?->provisionalHandledBy()?->value,
            // True when the pupil pays the instructor in cash at the lesson
            // (no Stripe). The app shows "Cash – collect £X" using `amount_pence`.
            'is_cash' => $lesson ? $lesson->order?->isCash() === true : null,
            // True when cancelling now is inside the 48-hour window, where the
            // cancel sheet offers the "Refund Lesson" tickbox.
            'is_late_cancellation' => $this->isLateCancellation($lesson),
            // True when the booking is still awaiting its first payment: cancelling
            // any lesson cancels the whole booking (`scope` is ignored).
            'awaiting_payment' => $lesson ? $lesson->order?->isPending() === true && ! $lesson->order->isImported() : null,
            // Alternative payment method (pupil pays the instructor directly, owner
            // switch on the pupil): what the instructor can do about payment.
            ...AlternativePayments::lessonFlags($lesson),
            'amount_pence' => $lesson?->amount_pence,
            'payment_breakdown' => $lesson?->paymentBreakdown(),
            'mileage' => $lesson?->mileage,
            'future_siblings_count' => $this->futureSiblingsCount($lesson),
            'has_open_offer' => $this->hasOpenOffer(),
        ];
    }

    /**
     * The lesson backing an active-booking or completed slot, or null. Resolved
     * for the whole booking family — booked, completed, draft (upfront, awaiting
     * payment) and reserved (weekly) — so the app can drive the unified move /
     * cancel UI (incl. the "this one / and future" prompt) identically across all
     * three active-booking statuses. Only resolved when `lessons` is eager-loaded.
     */
    protected function bookingLesson(): ?Lesson
    {
        if (! $this->resource->relationLoaded('lessons')) {
            return null;
        }

        $bookingStatuses = [
            CalendarItemStatus::BOOKED,
            CalendarItemStatus::COMPLETED,
            CalendarItemStatus::DRAFT,
            CalendarItemStatus::RESERVED,
        ];

        if (! in_array($this->status, $bookingStatuses, true)) {
            return null;
        }

        return $this->lessons->first();
    }

    /**
     * The student's name for any booking-family slot. Draft holds awaiting the
     * first payment are only named when staff or the instructor made the
     * booking: a learner's own unpaid checkout stays unnamed so it does not
     * look like a named booking.
     */
    protected function studentName(?Lesson $lesson): ?string
    {
        $order = $lesson?->order;

        if (! $order) {
            return null;
        }

        if ($this->status === CalendarItemStatus::DRAFT && ! AlternativePayments::isStaffOrInstructorBooking($order)) {
            return null;
        }

        $student = $order->student;

        if (! $student) {
            return null;
        }

        return trim($student->first_name.' '.$student->surname);
    }

    protected function isPaid(?Lesson $lesson): ?bool
    {
        if (! $lesson) {
            return null;
        }

        // Weekly: per-lesson payment status. Upfront: paid once the order is
        // confirmed — a draft is upfront-but-awaiting-payment, so never paid.
        // Imported: settled outside the platform, always paid. Cash: paid to
        // the instructor at the lesson, so never blocks sign-off.
        return $lesson->lessonPayment?->isPaid()
            ?? ($lesson->order?->isImported() === true
                || $lesson->order?->isCash() === true
                || ($lesson->order?->isUpfront() === true && ! $lesson->isDraft()));
    }

    protected function isLateCancellation(?Lesson $lesson): ?bool
    {
        if (! $lesson || $lesson->status === LessonStatus::COMPLETED) {
            return null;
        }

        return $lesson->isLateCancellation();
    }

    /**
     * Count future un-signed-off lessons in the same order — the lessons a bulk
     * reschedule would carry along with this one.
     */
    protected function futureSiblingsCount(?Lesson $lesson): int
    {
        if (! $lesson || ! $lesson->order) {
            return 0;
        }

        return $lesson->order->lessons
            ->filter(fn (Lesson $sibling) => $sibling->id !== $lesson->id
                && $sibling->date?->gt($lesson->date)
                && $sibling->payout === null
                && $sibling->status !== LessonStatus::COMPLETED)
            ->count();
    }

    protected function hasOpenOffer(): bool
    {
        if ($this->resource->relationLoaded('slotOffer')) {
            return $this->slotOffer?->status === SlotOfferStatus::Open;
        }

        return false;
    }
}
