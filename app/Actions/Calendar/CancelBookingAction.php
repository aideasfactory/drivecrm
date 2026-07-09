<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Models\Order;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\RefundRequiredNotification;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class CancelBookingAction
{
    public function __construct(
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * Cancel the booking attached to a calendar item. The student has left / no
     * longer wants lessons, so the lesson(s) are marked cancelled (kept for
     * history) and their calendar slots are freed from the diary. Future weekly
     * invoices stop automatically because the invoice sender skips cancelled
     * lessons. No Stripe void/refund happens here — paid lessons are reported to
     * Head Office for a manual refund.
     *
     * @param  bool  $applyToFutureInOrder  When true, also cancel every future un-signed-off lesson in the same order.
     * @return array{cancelled_count: int, refund_required_count: int}
     */
    public function __invoke(
        CalendarItem $item,
        string $reason,
        bool $applyToFutureInOrder,
        User $actor,
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

        $cancelSet = $this->buildCancelSet($anchorLesson, $applyToFutureInOrder);

        $affectedDates = collect();
        $paidLessons = collect();

        DB::transaction(function () use ($cancelSet, $reason, $affectedDates, $paidLessons): void {
            foreach ($cancelSet as $lesson) {
                if ($this->wasPaid($lesson)) {
                    $paidLessons->push($lesson);
                }

                $lesson->status = LessonStatus::CANCELLED;
                $lesson->cancellation_reason = $reason;
                $lesson->cancelled_at = now();

                $calendarItem = $lesson->calendarItem;

                // Detach the lesson first so the slot can be removed while the
                // cancelled lesson is retained for history.
                $lesson->calendar_item_id = null;
                $lesson->save();

                if ($calendarItem) {
                    $affectedDates->push($calendarItem->calendar?->date?->format('Y-m-d'));
                    ($this->deleteCalendarItem)($calendarItem);
                }
            }
        });

        $orderCancelled = $this->cancelOrderIfFullyCancelled($order);

        $this->invalidateCalendarCache($instructor?->id, $affectedDates);

        $refundRequiredCount = $paidLessons->count();

        $this->sendNotifications($cancelSet, $paidLessons, $order, $reason, $orderCancelled, $actor);

        return [
            'cancelled_count' => $cancelSet->count(),
            'refund_required_count' => $refundRequiredCount,
        ];
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
     * per-lesson payment is marked paid. Paid lessons need a manual refund.
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

        if ($hasOpenLessons || $order->status === OrderStatus::CANCELLED) {
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
     * Always email the student. Email Head Office only when a paid lesson was
     * cancelled (a manual refund is required).
     *
     * @param  Collection<int, Lesson>  $cancelSet
     * @param  Collection<int, Lesson>  $paidLessons
     */
    protected function sendNotifications(
        Collection $cancelSet,
        Collection $paidLessons,
        ?Order $order,
        string $reason,
        bool $orderCancelled,
        User $actor,
    ): void {
        $student = $order?->student;
        $instructor = $order?->instructor;
        $refundRequired = $paidLessons->isNotEmpty();

        if ($student?->user) {
            $student->user->notify(new BookingCancelledNotification(
                $student,
                $instructor,
                $cancelSet,
                $reason,
                $refundRequired,
            ));

            $student->logActivity(
                "Cancelled {$cancelSet->count()} lesson(s) in booking #{$order?->id}: {$reason}",
                'notification',
                [
                    'order_id' => $order?->id,
                    'cancelled_lesson_ids' => $cancelSet->pluck('id')->all(),
                    'refund_required' => $refundRequired,
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
                    $paidLessons,
                    $reason,
                ));
        }
    }
}
