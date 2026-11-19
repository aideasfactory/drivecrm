<?php

declare(strict_types=1);

namespace App\Actions\Reminder;

use App\Enums\LessonStatus;
use App\Enums\OrderStatus;
use App\Enums\ReminderType;
use App\Models\Lesson;
use App\Support\BookingPayments;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetLessonDayBeforeRemindersDueAction
{
    /**
     * Find tomorrow's lessons (UK date) due a "lesson tomorrow" push, but only
     * while UK time is inside the configured send window. A lesson qualifies
     * when it is PENDING (confirmed, not draft/cancelled/completed), dated, not
     * banked or provisional, on an order that is not cancelled/expired, its
     * learner has a push token, and it has not been reminded since the start
     * of the day before its date.
     *
     * Keying "already reminded" on the lesson's date (rather than the row
     * existing at all) means a lesson moved to another day is reminded again
     * for its new date, while a re-run on the same evening never resends.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(): Collection
    {
        $ukNow = CarbonImmutable::now(BookingPayments::timezone());

        if (! self::isInsideSendWindow($ukNow)) {
            return collect();
        }

        $tomorrow = $ukNow->addDay()->toDateString();

        return Lesson::query()
            ->where('status', LessonStatus::PENDING->value)
            ->whereDate('date', $tomorrow)
            ->whereNotNull('start_time')
            ->whereNull('banked_at')
            ->whereNull('provisional_at')
            ->whereHas('order', function ($query): void {
                $query->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::EXPIRED->value]);
            })
            ->whereHas('order.student.user', function ($query): void {
                $query->whereNotNull('expo_push_token');
            })
            ->whereDoesntHave('reminders', function ($query) use ($tomorrow): void {
                $query->where('type', ReminderType::LESSON_DAY_BEFORE->value)
                    ->where('sent_at', '>=', self::remindedSince($tomorrow));
            })
            ->with(['order.student.user', 'order.instructor.user', 'instructor.user'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Whether the given UK time falls inside [send_from, send_until).
     */
    public static function isInsideSendWindow(CarbonImmutable $ukNow): bool
    {
        $from = $ukNow->setTimeFromTimeString((string) config('lessons.day_before_reminder.send_from', '18:00'));
        $until = $ukNow->setTimeFromTimeString((string) config('lessons.day_before_reminder.send_until', '21:00'));

        return $ukNow >= $from && $ukNow < $until;
    }

    /**
     * A day-before reminder sent at or after this instant (UK midnight at the
     * start of the day before the lesson, as UTC) counts as already sent for
     * a lesson on `$lessonDate`.
     */
    public static function remindedSince(string $lessonDate): CarbonImmutable
    {
        return CarbonImmutable::parse($lessonDate, BookingPayments::timezone())
            ->subDay()
            ->startOfDay()
            ->utc();
    }
}
