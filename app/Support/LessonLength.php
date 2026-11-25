<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CalendarItem;
use Carbon\Carbon;

/**
 * Every lesson booked on Drive is 2 hours. Only driving tests (their own
 * `practical_test` diary entries) are shorter. Bookings and slot offers into
 * anything shorter are refused, and pupils are never shown shorter slots
 * (agreed with Head Office 2026-11-25). Imported (old-system) lessons keep
 * their original length; this only applies to new bookings.
 */
final class LessonLength
{
    public const MIN_MINUTES = 120;

    public const TOO_SHORT_MESSAGE = 'Lessons are 2 hours. This slot is too short — please choose a 2-hour slot.';

    public static function minutesBetween(?string $startTime, ?string $endTime): ?int
    {
        if (! $startTime || ! $endTime) {
            return null;
        }

        return (int) Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime), false);
    }

    public static function isTooShort(?string $startTime, ?string $endTime): bool
    {
        $minutes = self::minutesBetween($startTime, $endTime);

        return $minutes !== null && $minutes < self::MIN_MINUTES;
    }

    /**
     * A diary slot a lesson can't be booked into. Driving tests are exempt.
     */
    public static function slotIsTooShort(CalendarItem $calendarItem): bool
    {
        return ! $calendarItem->isPracticalTest()
            && self::isTooShort($calendarItem->start_time, $calendarItem->end_time);
    }
}
