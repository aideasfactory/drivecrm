<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Central accessor for booking and digital fee values.
 *
 * All fee-consuming code (pricing actions, order creation, package accessors,
 * checkout preview) should read fees through this helper. It keeps the
 * "override to zero" flag in a single place — flipping
 * `FEES_OVERRIDE_TO_ZERO=true` zeros every touchpoint at once.
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
     * Digital fee charged per lesson, in pounds (e.g., 3.99).
     */
    public static function digitalFeePerLesson(): float
    {
        if (self::isOverriddenToZero()) {
            return 0.0;
        }

        return (float) config('fees.digital_fee_per_lesson', 0);
    }

    /**
     * Digital fee charged per lesson, in pence (e.g., 399).
     */
    public static function digitalFeePerLessonPence(): int
    {
        return (int) round(self::digitalFeePerLesson() * 100);
    }

    /**
     * Total digital fee for a package of `$lessonsCount` lessons, in pence.
     */
    public static function digitalFeeTotalPence(int $lessonsCount): int
    {
        return self::digitalFeePerLessonPence() * max(0, $lessonsCount);
    }

    /**
     * Whether the master override is currently zeroing all fees.
     */
    public static function isOverriddenToZero(): bool
    {
        return (bool) config('fees.override_to_zero', false);
    }
}
