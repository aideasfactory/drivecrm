<?php

declare(strict_types=1);

namespace App\Actions\Lesson;

use App\Actions\Calendar\FindBlockingCalendarItemsAction;
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
        protected FindBlockingCalendarItemsAction $findBlockingCalendarItems,
    ) {}

    /**
     * Give back a diary item to every open, upcoming lesson that has lost it.
     *
     * Moving a lesson off a day where it was the only item used to delete the
     * day's calendar, which cascaded to the lesson's own item: the lesson kept
     * its old date and time but had no `calendar_item_id`, so the app could
     * only offer "View Pupil Profile" for it. Each such lesson gets a booked
     * item at its date and time (reserved for a weekly lesson not yet paid,
     * as when a banked lesson is rebooked); any empty availability it lands on
     * (or whose travel block it lands on) is removed so the time can't be
     * booked twice. If the time has since been taken by another lesson, a
     * blocked-out period or a test, the lesson is still restored but flagged
     * provisional, so it shows as clashing (Clashing lessons report, app
     * label) instead of silently double-booking.
     *
     * Left alone and reported: lessons dated before today, lessons whose
     * booking is not active (cancelled, completed, expired, awaiting payment),
     * banked lessons (no date) and driving tests. No emails, payments or
     * Stripe. Safe to re-run: restored lessons have a diary item again.
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

        $today = now()->toDateString();
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
                'outcome' => '',
            ];

            $skipReason = match (true) {
                $lesson->order === null => 'skipped: no booking',
                ! $lesson->order->isActive() => 'skipped: booking is '.$lesson->order->status->value,
                $row['date'] < $today => 'skipped: in the past (sign off or cancel it in the CRM)',
                $instructorId === null => 'skipped: no instructor',
                default => null,
            };

            if ($skipReason !== null) {
                $rows[] = ['outcome' => $skipReason] + $row;

                continue;
            }

            if ($dryRun) {
                $clashIds = $this->clashingItemIds($lesson, $instructorId);
                $row['outcome'] = 'to restore'.$this->clashNote($clashIds);
                $rows[] = $row;

                continue;
            }

            $restored = $this->restore($lesson, $instructorId);

            if ($restored === null) {
                $rows[] = ['outcome' => 'skipped: changed while running'] + $row;

                continue;
            }

            $row['calendar_item_id'] = $restored['calendar_item_id'];
            $row['outcome'] = 'restored'.$this->clashNote($restored['clash_ids']);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{calendar_item_id: int, clash_ids: list<int>}|null Null when the lesson no longer needs restoring
     */
    protected function restore(Lesson $lesson, int $instructorId): ?array
    {
        $date = $lesson->date->format('Y-m-d');
        $startTime = $lesson->start_time->format('H:i');
        $endTime = $lesson->end_time->format('H:i');

        $status = $lesson->order?->isWeekly() === true && $lesson->lessonPayment?->status !== PaymentStatus::PAID
            ? CalendarItemStatus::RESERVED
            : CalendarItemStatus::BOOKED;

        $result = DB::transaction(function () use ($lesson, $instructorId, $date, $startTime, $endTime, $status): ?array {
            $lockedLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->first();

            if ($lockedLesson === null
                || $lockedLesson->calendar_item_id !== null
                || $lockedLesson->status !== LessonStatus::PENDING
                || $lockedLesson->banked_at !== null
                || $lockedLesson->date?->format('Y-m-d') !== $date) {
                return null;
            }

            $calendar = Calendar::query()->firstOrCreate([
                'instructor_id' => $instructorId,
                'date' => $date,
            ]);

            $this->removeOverlappedAvailability($calendar->id, $startTime, $endTime);

            $calendarItem = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => false,
                'status' => $status,
                'item_type' => CalendarItemType::Slot,
            ]);

            $clashIds = ($this->findBlockingCalendarItems)($calendarItem)->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();

            $lockedLesson->forceFill([
                'calendar_item_id' => $calendarItem->id,
                ...($clashIds !== [] ? ['provisional_at' => now()] : []),
            ])->save();

            return ['calendar_item_id' => $calendarItem->id, 'clash_ids' => $clashIds];
        });

        if ($result === null) {
            return null;
        }

        $this->calendarService->invalidateCalendarCache($instructorId, $date);

        Log::info('Restored a missing diary item for a lesson', [
            'lesson_id' => $lesson->id,
            'calendar_item_id' => $result['calendar_item_id'],
            'instructor_id' => $instructorId,
            'date' => $date,
            'provisional_clash_item_ids' => $result['clash_ids'],
        ]);

        return $result;
    }

    /**
     * Delete each open, unbooked slot (and its travel block) the lesson's time
     * overlaps, directly or through the slot's travel block.
     */
    protected function removeOverlappedAvailability(int $calendarId, string $startTime, string $endTime): void
    {
        CalendarItem::query()
            ->where('calendar_id', $calendarId)
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

    /**
     * What the lesson would clash with once back in the diary (dry run).
     *
     * @return list<int>
     */
    protected function clashingItemIds(Lesson $lesson, int $instructorId): array
    {
        $calendarId = Calendar::query()
            ->where('instructor_id', $instructorId)
            ->whereDate('date', $lesson->date->format('Y-m-d'))
            ->value('id');

        if ($calendarId === null) {
            return [];
        }

        return $this->findBlockingCalendarItems
            ->forTimeRange((int) $calendarId, $lesson->start_time->format('H:i'), $lesson->end_time->format('H:i'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $clashIds
     */
    protected function clashNote(array $clashIds): string
    {
        return $clashIds === []
            ? ''
            : ' (provisional: clashes with diary item #'.implode(', #', $clashIds).')';
    }
}
