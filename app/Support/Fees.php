<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\Carbon;

/**
 * Central accessor for booking and digital fee values.
 *
 * All fee-consuming code (pricing actions, order creation, package accessors,
 * checkout preview) should read fees through this helper. It keeps the
 * "override to zero" flag in a single place — flipping
 * `FEES_OVERRIDE_TO_ZERO=true` zeros every touchpoint at once.
 *
 * The digital fee is a rate per hour of lesson time. A package is that rate
 * multiplied by the total hours booked. When the slot length is not known,
 * each lesson uses the standard diary length (2 hours).
 */
final class Fees
{
    /**
     * Booking fee in pounds (e.g., 19.99).
     */
    public static function bookingFee(): float
    {
        if (self::isOverriddenToZero()) {
            return 0.0;
        }

        return (float) config('fees.booking_fee', 0);
    }

    /**
     * Booking fee in pence (e.g., 1999).
     */
    public static function bookingFeePence(): int
    {
        return (int) round(self::bookingFee() * 100);
    }

    /**
     * Digital fee charged per hour of lesson time, in pounds (e.g., 1.80).
     *
     * A runtime override of the legacy `fees.digital_fee_per_lesson` key
     * (without also setting `fees.digital_fee_per_hour`) still supplies this
     * rate. Both keys hold the same value after config is loaded.
     */
    public static function digitalFeePerHour(): float
    {
        if (self::isOverriddenToZero()) {
            return 0.0;
        }

        $perHour = config('fees.digital_fee_per_hour');
        $legacy = config('fees.digital_fee_per_lesson');

        if ($perHour === null) {
            return (float) ($legacy ?? 1.80);
        }

        if ($legacy !== null && (float) $legacy !== (float) $perHour) {
            return (float) $legacy;
        }

        return (float) $perHour;
    }

    /**
     * Digital fee charged per hour of lesson time, in pence (e.g., 180).
     */
    public static function digitalFeePerHourPence(): int
    {
        return (int) round(self::digitalFeePerHour() * 100);
    }

    /**
     * Customer-facing rate label, e.g. "£1.80/hour".
     */
    public static function perHourLabel(): string
    {
        return '£'.number_format(self::digitalFeePerHour(), 2).'/hour';
    }

    /**
     * Minutes of one lesson. Uses the slot when both times parse to a
     * positive length; otherwise the standard 2-hour diary slot.
     */
    public static function lessonMinutes(?string $startTime, ?string $endTime): int
    {
        if ($startTime && $endTime) {
            try {
                $minutes = (int) round(Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime), false));

                if ($minutes > 0) {
                    return $minutes;
                }
            } catch (\Throwable) {
                // Unparseable times use the standard lesson length.
            }
        }

        return max(1, (int) config('fees.standard_lesson_minutes', 120));
    }

    /**
     * Total minutes booked: every lesson in the package uses the same slot length.
     */
    public static function bookedMinutes(int $lessonsCount, ?string $startTime = null, ?string $endTime = null): int
    {
        return self::lessonMinutes($startTime, $endTime) * max(0, $lessonsCount);
    }

    /**
     * Total hours booked, rounded to 2 decimal places.
     */
    public static function bookedHours(int $lessonsCount, ?string $startTime = null, ?string $endTime = null): float
    {
        return round(self::bookedMinutes($lessonsCount, $startTime, $endTime) / 60, 2);
    }

    /**
     * Total digital fee for the booking, in pence.
     *
     * Pence are derived from the hourly rate times booked minutes so a
     * 2-hour lesson at £1.80 is exactly £3.60.
     */
    public static function digitalFeeTotalPence(int $lessonsCount, ?string $startTime = null, ?string $endTime = null): int
    {
        $minutes = self::bookedMinutes($lessonsCount, $startTime, $endTime);

        return (int) round(self::digitalFeePerHourPence() * $minutes / 60);
    }

    /**
     * Total digital fee for the booking, in pounds.
     */
    public static function digitalFeeTotal(int $lessonsCount, ?string $startTime = null, ?string $endTime = null): float
    {
        return round(self::digitalFeeTotalPence($lessonsCount, $startTime, $endTime) / 100, 2);
    }

    /**
     * Whether the master override is currently zeroing all fees.
     */
    public static function isOverriddenToZero(): bool
    {
        return (bool) config('fees.override_to_zero', false);
    }
}
