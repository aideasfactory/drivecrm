<?php

declare(strict_types=1);

namespace App\Actions\Calendar;

use App\Actions\Instructor\DeleteCalendarItemAction;
use App\Enums\CalendarItemStatus;
use App\Enums\CalendarItemType;
use App\Enums\LessonStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Calendar;
use App\Models\CalendarItem;
use App\Models\Lesson;
use App\Support\BookingPayments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RelinkOrphanedLessonsAction
{
    public function __construct(
        protected FindBlockingCalendarItemsAction $findBlockingCalendarItems,
        protected DescribeBlockingCalendarItemAction $describeBlockingCalendarItem,
        protected DeleteCalendarItemAction $deleteCalendarItem,
    ) {}

    /**
     * Give booked lessons that lost their diary slot a slot again, on the
     * lesson's own (current) date and time — where it was before the failed
     * move. Lessons lost their slot when a move deleted the old diary day
     * before saving the item to the new day (fixed in UpdateCalendarItemAction).
     *
     * Found: pending, dated, not banked lessons with no `calendar_item_id`.
     * A lesson whose time is now taken (another lesson, blocked time, a
     * practical test, or open availability with a slot offer) is reported and
     * skipped. Open availability underneath is removed, as a booking would.
     * Nothing touches orders, payments, payouts or Stripe; no emails.
     *
     * @param  array<int, int>  $onlyLessonIds  Limit to these lessons (empty = all)
     * @return array{counts: array{found: int, relinked: int, clashing: int, order_closed: int, payment_issues: int, possible_duplicates: int, open_slots_removed: int}, lessons: array<int, array<string, mixed>>, dates_by_instructor: array<int, array<string, true>>}
     */
    public function __invoke(bool $dryRun, array $onlyLessonIds = []): array
    {
        $result = [
            'counts' => ['found' => 0, 'relinked' => 0, 'clashing' => 0, 'order_closed' => 0, 'payment_issues' => 0, 'possible_duplicates' => 0, 'open_slots_removed' => 0],
            'lessons' => [],
            'dates_by_instructor' => [],
        ];

        foreach ($this->findOrphanedLessons($onlyLessonIds) as $lesson) {
            $result['counts']['found']++;

            $date = $lesson->date->toDateString();
            $startTime = (string) $lesson->getRawOriginal('start_time');
            $endTime = (string) $lesson->getRawOriginal('end_time');
            $calendar = Calendar::query()->where('instructor_id', $lesson->instructor_id)->whereDate('date', $date)->first();

            $clashes = $calendar ? $this->describeClashes($calendar->id, $startTime, $endTime) : [];
            $openSlots = $calendar ? $this->openSlotsUnder($calendar->id, $startTime, $endTime) : collect();
            $paymentIssues = $this->paymentIssues($lesson);
            $duplicates = $this->possibleDuplicates($lesson);
            $orderClosed = $lesson->order?->isClosed() === true;

            $result['counts']['payment_issues'] += (int) ($paymentIssues !== []);
            $result['counts']['possible_duplicates'] += (int) ($duplicates !== []);

            $outcome = match (true) {
                $orderClosed => 'skipped: order '.$lesson->order->status->value,
                $clashes !== [] => 'skipped: clash',
                default => $dryRun ? 'to relink' : 'relinked',
            };

            if ($orderClosed) {
                $result['counts']['order_closed']++;
            } elseif ($clashes !== []) {
                $result['counts']['clashing']++;
            } else {
                if (! $dryRun) {
                    $this->relink($lesson, $date, $startTime, $endTime, $openSlots);
                    $result['dates_by_instructor'][(int) $lesson->instructor_id][$date] = true;
                }

                $result['counts']['relinked']++;
                $result['counts']['open_slots_removed'] += $openSlots->count();
            }

            $student = $lesson->order?->student;

            $result['lessons'][] = [
                'lesson_id' => $lesson->id,
                'instructor' => $lesson->instructor_id.' '.($lesson->instructor?->user?->name ?? ''),
                'pupil' => trim(($student?->first_name ?? '').' '.($student?->surname ?? '')),
                'date' => $date.($lesson->date->isPast() && ! $lesson->date->isToday() ? ' (past)' : ''),
                'time' => substr($startTime, 0, 5).'–'.substr($endTime, 0, 5),
                'payment' => $this->paymentLabel($lesson),
                'payment_issues' => implode('; ', $paymentIssues),
                'clashes' => implode('; ', $clashes),
                'open_slots_removed' => $openSlots->count(),
                'possible_duplicates' => implode('; ', $duplicates),
                'outcome' => $outcome,
            ];
        }

        if (! $dryRun && $result['counts']['relinked'] > 0) {
            Log::info('diary:relink-orphaned-lessons relinked lessons to new diary slots', $result['counts']);
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $onlyLessonIds
     * @return Collection<int, Lesson>
     */
    protected function findOrphanedLessons(array $onlyLessonIds): Collection
    {
        return Lesson::query()
            ->whereNull('calendar_item_id')
            ->where('status', LessonStatus::PENDING)
            ->whereNull('banked_at')
            ->whereNotNull('date')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->whereNotNull('instructor_id')
            ->when($onlyLessonIds !== [], fn ($query) => $query->whereKey($onlyLessonIds))
            ->with(['order.student', 'instructor.user', 'lessonPayment', 'payout'])
            ->orderBy('instructor_id')
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * What the lesson's time is now taken by: anything that blocks a booking,
     * plus open availability that carries a slot offer (it can't just be removed).
     *
     * @return array<int, string>
     */
    protected function describeClashes(int $calendarId, string $startTime, string $endTime): array
    {
        $clashes = $this->findBlockingCalendarItems->forTimeRange($calendarId, $startTime, $endTime)
            ->map(fn (CalendarItem $item): string => ($this->describeBlockingCalendarItem)($item))
            ->all();

        $offeredSlots = CalendarItem::query()
            ->where('calendar_id', $calendarId)
            ->overlapping($startTime, $endTime)
            ->emptyAvailability()
            ->whereHas('slotOffer')
            ->get()
            ->map(fn (CalendarItem $item): string => 'Open slot with a slot offer ('.substr((string) $item->start_time, 0, 5).'–'.substr((string) $item->end_time, 0, 5).')')
            ->all();

        return [...$clashes, ...$offeredSlots];
    }

    /**
     * Open availability (no slot offer) under the lesson's time — taken over by
     * the restored booking, the same as booking onto availability.
     *
     * @return Collection<int, CalendarItem>
     */
    protected function openSlotsUnder(int $calendarId, string $startTime, string $endTime): Collection
    {
        return CalendarItem::query()
            ->where('calendar_id', $calendarId)
            ->overlapping($startTime, $endTime)
            ->emptyAvailability()
            ->whereDoesntHave('slotOffer')
            ->get();
    }

    protected function paymentLabel(Lesson $lesson): string
    {
        $order = $lesson->order;

        return match ($order?->payment_mode) {
            PaymentMode::IMPORTED => 'Imported (£0, no Stripe)',
            PaymentMode::UPFRONT => $order->stripe_payment_intent_id
                ? 'Paid upfront (Stripe)'
                : 'Upfront, not paid (order '.$order->status->value.')',
            PaymentMode::WEEKLY => 'Weekly: '.($lesson->lessonPayment?->status->value ?? 'no payment row'),
            PaymentMode::CASH => 'Cash',
            default => $order?->payment_mode?->value ?? 'No order',
        };
    }

    /**
     * Anything about the lesson's money that needs a look, whether or not it is relinked.
     *
     * @return array<int, string>
     */
    protected function paymentIssues(Lesson $lesson): array
    {
        $issues = [];
        $order = $lesson->order;
        $payment = $lesson->lessonPayment;

        if ($order?->isClosed()) {
            $issues[] = 'order is '.$order->status->value;
        }

        if ($lesson->payout !== null) {
            $issues[] = 'payout exists ('.$lesson->payout->status->value.') on a lesson not signed off';
        }

        if ($payment?->status === PaymentStatus::REFUNDED) {
            $issues[] = 'lesson payment refunded';
        }

        if ($order?->payment_mode === PaymentMode::WEEKLY && $payment?->status === PaymentStatus::DUE) {
            if ($payment->invoice_cancelled_at !== null) {
                $issues[] = 'weekly invoice voided as overdue';
            }

            $expectedDueDate = BookingPayments::weeklyPaymentDueDate($lesson->date->toDateString(), (string) $lesson->getRawOriginal('start_time'));

            if ($payment->stripe_invoice_id === null && $payment->due_date?->toDateString() !== $expectedDueDate) {
                $issues[] = 'weekly due date '.($payment->due_date?->toDateString() ?? 'none').' (expected '.$expectedDueDate.')';
            }
        }

        $isPaid = ($order?->payment_mode === PaymentMode::UPFRONT && $order->stripe_payment_intent_id)
            || $payment?->status === PaymentStatus::PAID;

        if ($isPaid && $lesson->date->isPast() && ! $lesson->date->isToday()) {
            $issues[] = 'paid, date passed, not signed off';
        }

        return $issues;
    }

    /**
     * The same pupil's other live lessons within 6 days — the instructor may
     * have rebooked them after the move appeared to fail.
     *
     * @return array<int, string>
     */
    protected function possibleDuplicates(Lesson $lesson): array
    {
        $studentId = $lesson->order?->student_id;

        if ($studentId === null) {
            return [];
        }

        return Lesson::query()
            ->whereKeyNot($lesson->id)
            ->whereHas('order', fn ($query) => $query->where('student_id', $studentId))
            ->whereIn('status', [LessonStatus::PENDING, LessonStatus::DRAFT, LessonStatus::COMPLETED])
            ->whereBetween('date', [$lesson->date->copy()->subDays(6)->toDateString(), $lesson->date->copy()->addDays(6)->toDateString()])
            ->orderBy('date')
            ->get()
            ->map(fn (Lesson $other): string => "#{$other->id} {$other->date->toDateString()} ".substr((string) $other->getRawOriginal('start_time'), 0, 5)." ({$other->status->value})")
            ->all();
    }

    /**
     * @param  Collection<int, CalendarItem>  $openSlots
     */
    protected function relink(Lesson $lesson, string $date, string $startTime, string $endTime, Collection $openSlots): void
    {
        DB::transaction(function () use ($lesson, $date, $startTime, $endTime, $openSlots): void {
            $calendar = Calendar::query()->firstOrCreate([
                'instructor_id' => $lesson->instructor_id,
                'date' => $date,
            ]);

            $item = CalendarItem::create([
                'calendar_id' => $calendar->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'is_available' => false,
                'status' => CalendarItemStatus::BOOKED,
                'item_type' => $lesson->is_practical_test ? CalendarItemType::PracticalTest : CalendarItemType::Slot,
                'student_id' => $lesson->is_practical_test ? $lesson->order?->student_id : null,
            ]);

            $lesson->calendar_item_id = $item->id;
            $lesson->save();

            // The day now holds the restored booking, so removing these never empties it.
            foreach ($openSlots as $slot) {
                ($this->deleteCalendarItem)($slot);
            }

            Log::info('diary:relink-orphaned-lessons relinked a lesson', [
                'lesson_id' => $lesson->id,
                'calendar_item_id' => $item->id,
                'removed_open_slot_ids' => $openSlots->pluck('id')->all(),
            ]);
        });
    }
}
