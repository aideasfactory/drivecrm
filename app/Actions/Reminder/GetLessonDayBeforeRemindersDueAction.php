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
     * UK time (inclusive) from which reminders for tomorrow's lessons go out.
     */
    public const SEND_WINDOW_START = '18:00';

    /**
     * UK time (exclusive) after which no more reminders go out that evening:
     * a run missed in the window is not back-filled late at night.
     */
    public const SEND_WINDOW_END = '21:00';

    /**
     * Find tomorrow's lessons (UK date) that are due a day-before push: the
     * lesson is open (pending, not provisional or banked) on a confirmed
     * booking (active or completed order), the learner's user has an Expo
     * push token, and no reminder has been sent for it today. Returns nothing
     * outside the UK send window.
     *
     * Lesson date/start_time are UK wall-clock values. A reminder row from an
     * earlier day does not count, so a lesson moved to a later date is
     * reminded again the evening before its new date.
     *
     * @return Collection<int, Lesson>
     */
    public function __invoke(): Collection
    {
        $now = CarbonImmutable::now(BookingPayments::timezone());
        $time = $now->format('H:i');

        if ($time < self::SEND_WINDOW_START || $time >= self::SEND_WINDOW_END) {
            return collect();
        }

        return Lesson::query()
            ->whereDate('date', $now->addDay()->toDateString())
            ->where('status', LessonStatus::PENDING->value)
            ->whereNull('provisional_at')
            ->whereNull('banked_at')
            ->whereNotNull('start_time')
            ->whereHas('order', function ($query): void {
                $query->whereIn('status', [OrderStatus::ACTIVE->value, OrderStatus::COMPLETED->value])
                    ->whereHas('student.user', function ($query): void {
                        $query->whereNotNull('expo_push_token');
                    });
            })
            ->whereDoesntHave('reminders', function ($query) use ($now): void {
                $query->where('type', ReminderType::LESSON_DAY_BEFORE->value)
                    ->where('sent_at', '>=', self::startOfUkDay($now));
            })
            ->with(['order.student.user', 'order.instructor.user', 'instructor.user'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Start of the given UK day, in the app timezone, for comparing against
     * stored timestamps.
     */
    public static function startOfUkDay(CarbonImmutable $ukNow): CarbonImmutable
    {
        return $ukNow->startOfDay()->setTimezone(config('app.timezone'));
    }
}
