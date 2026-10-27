<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Student\Lesson\RecalculateStudentLessonNumbersAction;
use App\Actions\Student\Order\CreateDraftCalendarItemsAction;
use App\Enums\CalendarItemStatus;
use App\Enums\PaymentStatus;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Models\LessonPayment;
use App\Models\User;
use App\Notifications\BankedLessonRebookedNotification;
use App\Services\InstructorCalendarService;
use App\Support\BookingPayments;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RebookBankedLessonAction
{
    public function __construct(
        protected CreateDraftCalendarItemsAction $createDraftCalendarItems,
        protected RecalculateStudentLessonNumbersAction $recalculateStudentLessonNumbers,
    ) {}

    /**
     * Put a banked lesson back in its instructor's diary at a new date and
     * time. The slot is taken the same way a booking takes one (an open slot
     * at exactly that time is reused, otherwise a new one is made, and a
     * clash with another lesson, blocked-out time or a test is refused), then
     * marked booked, or reserved for a weekly lesson that isn't paid yet.
     * Open availability the lesson lands on is taken over, as when a lesson
     * is moved.
     *
     * The price and payment are untouched. A weekly lesson still owed is due
     * relative to its new time, so `lessons:send-invoices` invoices it as
     * normal before the lesson.
     *
     * @throws ValidationException When the lesson isn't banked or the time isn't free
     */
    public function __invoke(Lesson $lesson, string $date, string $startTime, string $endTime, User $actor): Lesson
    {
        $lesson->loadMissing(['order.student.user', 'order.instructor.user', 'lessonPayment']);

        $order = $lesson->order;

        if (! $lesson->isBanked() || ! $order?->isActive()) {
            throw ValidationException::withMessages([
                'lesson' => 'This lesson is not waiting to be rebooked.',
            ]);
        }

        if (! $lesson->instructor_id) {
            throw ValidationException::withMessages([
                'lesson' => 'This lesson has no instructor to rebook it with.',
            ]);
        }

        $date = Carbon::parse($date)->format('Y-m-d');

        DB::transaction(function () use ($lesson, $order, $date, $startTime, $endTime): void {
            $lockedLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->first();

            if (! $lockedLesson?->isBanked()) {
                throw ValidationException::withMessages([
                    'lesson' => 'This lesson has just been rebooked. Refresh to see it.',
                ]);
            }

            [$calendarItemId] = ($this->createDraftCalendarItems)($lesson->instructor_id, $date, $startTime, $endTime, 1);

            $calendarItem = CalendarItem::query()->with('travelItem')->findOrFail($calendarItemId);

            $status = $order->isWeekly() && $lesson->lessonPayment?->status !== PaymentStatus::PAID
                ? CalendarItemStatus::RESERVED
                : CalendarItemStatus::BOOKED;

            $calendarItem->update(['status' => $status, 'is_available' => false]);
            $calendarItem->travelItem?->update(['status' => $status]);

            $this->consumeOverlappedAvailability($calendarItem, $startTime, $endTime);

            $lesson->forceFill([
                'calendar_item_id' => $calendarItem->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'banked_at' => null,
                'banked_reason' => null,
                'banked_by_user_id' => null,
            ])->save();

            if ($order->isWeekly()) {
                LessonPayment::query()
                    ->where('lesson_id', $lesson->id)
                    ->where('status', PaymentStatus::DUE)
                    ->whereNull('stripe_invoice_id')
                    ->update(['due_date' => BookingPayments::weeklyPaymentDueDate($date, $startTime)]);
            }
        });

        $lesson->refresh();

        if ($order->student_id) {
            ($this->recalculateStudentLessonNumbers)($order->student_id);
        }

        app(InstructorCalendarService::class)->invalidateCalendarCache($lesson->instructor_id, $date);

        $this->recordAndNotify($lesson, $actor);

        return $lesson;
    }

    /**
     * Delete any open, unbooked slot (and its travel block) the rebooked
     * lesson now overlaps, so the same time can't be booked twice.
     */
    protected function consumeOverlappedAvailability(CalendarItem $calendarItem, string $startTime, string $endTime): void
    {
        CalendarItem::query()
            ->where('calendar_id', $calendarItem->calendar_id)
            ->whereKeyNot($calendarItem->id)
            ->where(fn ($query) => $query->whereNull('parent_item_id')->orWhere('parent_item_id', '!=', $calendarItem->id))
            ->overlapping($startTime, $endTime)
            ->with('parentItem')
            ->get()
            ->map(fn (CalendarItem $item): ?CalendarItem => match (true) {
                $item->isTravel() && $item->parentItem?->isEmptyAvailability() => $item->parentItem,
                $item->isEmptyAvailability() => $item,
                default => null,
            })
            ->filter()
            ->unique('id')
            ->each(function (CalendarItem $slot): void {
                $slot->travelItem?->delete();
                $slot->delete();
            });
    }

    protected function recordAndNotify(Lesson $lesson, User $actor): void
    {
        $order = $lesson->order;
        $student = $order?->student;

        Log::info('Banked lesson rebooked', [
            'lesson_id' => $lesson->id,
            'order_id' => $order?->id,
            'date' => $lesson->date?->toDateString(),
            'rebooked_by_user_id' => $actor->id,
        ]);

        if (! $student) {
            return;
        }

        $when = $lesson->date?->format('D j M Y').' '.$lesson->start_time?->format('H:i').'–'.$lesson->end_time?->format('H:i');

        $student->logActivity(
            "Rebooked kept lesson #{$lesson->student_lesson_number} (booking #{$order?->id}) for {$when}",
            'booking',
            [
                'order_id' => $order?->id,
                'lesson_id' => $lesson->id,
                'date' => $lesson->date?->toDateString(),
                'start_time' => $lesson->start_time?->format('H:i'),
                'end_time' => $lesson->end_time?->format('H:i'),
                'rebooked_by_user_id' => $actor->id,
            ],
            "Rebooked kept lesson for {$when}",
        );

        $student->user?->notify(new BankedLessonRebookedNotification(
            $lesson,
            $student,
            $order?->instructor,
            $order?->package_name ?? 'lesson',
        ));
    }
}
