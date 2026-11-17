<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\PaymentStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Services\InstructorCalendarService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestoreMissingLessonDiaryItemsAction
{
    public function __construct(
        protected InstructorCalendarService $calendarService,
    ) {}

    /**
     * Give back a diary item to every open, dated lesson that has lost it.
     *
     * Moving a lesson off a day where it was the only item used to delete the
     * day's calendar, which cascaded to the lesson's own item: the lesson kept
     * its old date and time but had no `calendar_item_id`, so the app could
     * only offer "View Pupil Profile" for it. Each such lesson gets a booked
     * item at its date and time (reserved for a weekly lesson not yet paid,
     * as when a banked lesson is rebooked); any empty availability it lands on
     * is removed so the time can't be booked twice. Banked lessons (no date)
     * and driving tests are left alone. No emails, payments or Stripe.
     *
     * @return list<array{lesson_id: int, instructor_id: int|null, student_id: int|null, date: string, time: string, payment_mode: string|null, calendar_item_id: int|null, outcome: string}>
     */
    public function __invoke(bool $dryRun): array
    {
        $lessons = Lesson::query()
            ->with(['order', 'lessonPayment'])
            ->whereNull('calendar_item_id')
            ->whereNull('banked_at')
            ->where('status', LessonStatus::PENDING)
            ->where('is_practical_test', false)
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $rows = [];

        foreach ($lessons as $lesson) {
            $instructorId = $lesson->instructor_id ?? $lesson->order?->instructor_id;
            $row = [
                'lesson_id' => $lesson->id,
                'instructor_id' => $instructorId,
                'student_id' => $lesson->order?->student_id,
                'date' => $lesson->date->format('Y-m-d'),
                'time' => $lesson->start_time->format('H:i').'–'.$lesson->end_time->format('H:i'),
                'payment_mode' => $lesson->order?->payment_mode?->value,
                'calendar_item_id' => null,
                'outcome' => $dryRun ? 'to restore' : 'restored',
            ];

            if ($instructorId === null) {
                $rows[] = ['outcome' => 'skipped: no instructor'] + $row;

                continue;
            }

            if (! $dryRun) {
                $row['calendar_item_id'] = $this->restore($lesson, $instructorId);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function restore(Lesson $lesson, int $instructorId): int
    {
        $date = $lesson->date->format('Y-m-d');
        $startTime = $lesson->start_time->format('H:i');
        $endTime = $lesson->end_time->format('H:i');

        $status = $lesson->order?->isWeekly() === true && $lesson->lessonPayment?->status !== PaymentStatus::PAID
            ? CalendarItemStatus::RESERVED
            : CalendarItemStatus::BOOKED;

        $calendarItem = DB::transaction(function () use ($lesson, $instructorId, $date, $startTime, $endTime, $status): CalendarItem {
            $calendar = Calendar::query()->firstOrCreate([
                'instructor_id' => $instructorId,
                'date' => $date,
            ]);

            $emptySlots = CalendarItem::query()
                ->where('calendar_id', $calendar->id)
                ->emptyAvailability()
                ->overlapping($startTime, $endTime)
                ->with('travelItem')
                ->get();

            foreach ($emptySlots as $slot) {
                $slot->travelItem?->delete();
                $slot->delete();
            }

            $calendarItem = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => false,
                'status' => $status,
                'item_type' => CalendarItemType::Slot,
            ]);

            $lesson->forceFill(['calendar_item_id' => $calendarItem->id])->save();

            return $calendarItem;
        });

        $this->calendarService->invalidateCalendarCache($instructorId, $date);

        Log::info('Restored a missing diary item for a lesson', [
            'lesson_id' => $lesson->id,
            'calendar_item_id' => $calendarItem->id,
            'instructor_id' => $instructorId,
            'date' => $date,
        ]);

        return $calendarItem->id;
    }
}
