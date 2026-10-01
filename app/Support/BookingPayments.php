<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Rules for how long an unpaid booking holds its diary slots and when weekly
 * lesson payments fall due. Values come from `config/booking_payments.php`.
 *
 * Lesson dates and times are stored as UK wall-clock values, so they are read
 * in the configured booking timezone and returned in the app timezone.
 */
final class BookingPayments
{
    /**
     * Stripe only accepts Checkout `expires_at` values between 30 minutes and
     * 24 hours from creation. These margins keep us safely inside that range.
     */
    private const STRIPE_MIN_SESSION_MINUTES = 31;

    private const STRIPE_MAX_SESSION_MINUTES = 23 * 60 + 50;

    /**
     * Hold for a learner booking and paying there and then (booking form or
     * mobile app). The learner is already at checkout, so the emailed-link
     * minimum does not apply.
     */
    public static function learnerHoldExpiresAt(): CarbonImmutable
    {
        return self::now()->addMinutes(self::learnerHoldMinutes());
    }

    /**
     * Hold for a bookings-team booking: until the coming midnight, UK time.
     */
    public static function staffHoldExpiresAt(): CarbonImmutable
    {
        $midnight = self::now()
            ->setTimezone(self::timezone())
            ->addDay()
            ->startOfDay()
            ->setTimezone(config('app.timezone'));

        return self::atLeastMinimum($midnight);
    }

    /**
     * Hold for an instructor booking: until the first payment is due, i.e. the
     * configured number of hours before the first lesson starts.
     */
    public static function instructorHoldExpiresAt(string $firstLessonDate, ?string $firstLessonStartTime): CarbonImmutable
    {
        $deadline = self::lessonStartsAt($firstLessonDate, $firstLessonStartTime)
            ->subHours((int) config('booking_payments.instructor_hold_hours_before_lesson', 48));

        return self::atLeastMinimum($deadline);
    }

    /**
     * When a weekly lesson payment is due.
     */
    public static function weeklyPaymentDueAt(string $lessonDate, ?string $lessonStartTime): CarbonImmutable
    {
        return self::lessonStartsAt($lessonDate, $lessonStartTime)
            ->subHours(self::weeklyPaymentDueHoursBeforeLesson());
    }

    /**
     * The UK calendar date a weekly lesson payment is due on (for `lesson_payments.due_date`).
     */
    public static function weeklyPaymentDueDate(string $lessonDate, ?string $lessonStartTime): string
    {
        return self::weeklyPaymentDueAt($lessonDate, $lessonStartTime)
            ->setTimezone(self::timezone())
            ->toDateString();
    }

    /**
     * Expiry to send to Stripe for a Checkout session backing a hold. Stripe
     * cannot expire a session sooner than 30 minutes, so short holds are
     * closed by `orders:release-expired-holds` instead; long holds get a
     * fresh session each time the emailed link is opened.
     */
    public static function stripeCheckoutExpiresAt(?CarbonInterface $holdExpiresAt): CarbonImmutable
    {
        $now = self::now();
        $earliest = $now->addMinutes(self::STRIPE_MIN_SESSION_MINUTES);
        $latest = $now->addMinutes(self::STRIPE_MAX_SESSION_MINUTES);

        if ($holdExpiresAt === null) {
            return $latest;
        }

        $holdExpiresAt = CarbonImmutable::instance($holdExpiresAt);

        if ($holdExpiresAt->lessThan($earliest)) {
            return $earliest;
        }

        return $holdExpiresAt->greaterThan($latest) ? $latest : $holdExpiresAt;
    }

    /**
     * Whether cancelling a lesson now falls inside the late-cancellation window
     * (fewer than `late_cancellation_hours` before it starts, or already started).
     */
    public static function isLateCancellation(string $lessonDate, ?string $lessonStartTime): bool
    {
        $cutoff = self::lessonStartsAt($lessonDate, $lessonStartTime)
            ->subHours(self::lateCancellationHours());

        return self::now()->greaterThanOrEqualTo($cutoff);
    }

    public static function lateCancellationHours(): int
    {
        return (int) config('booking_payments.late_cancellation_hours', 48);
    }

    public static function learnerHoldMinutes(): int
    {
        return (int) config('booking_payments.learner_hold_minutes', 10);
    }

    public static function weeklyPaymentDueHoursBeforeLesson(): int
    {
        return (int) config('booking_payments.weekly_payment_due_hours_before_lesson', 48);
    }

    public static function timezone(): string
    {
        return (string) config('booking_payments.timezone', 'Europe/London');
    }

    /**
     * Format a hold deadline for learners, e.g. "3:15pm on Tuesday 29 September".
     * Midnight reads as the end of the previous day ("midnight on Monday 28 September").
     */
    public static function formatDeadline(CarbonInterface $deadline): string
    {
        $local = CarbonImmutable::instance($deadline)->setTimezone(self::timezone());

        if ($local->format('H:i:s') === '00:00:00') {
            return 'midnight on '.$local->subDay()->format('l j F');
        }

        return $local->format('g:ia \o\n l j F');
    }

    private static function lessonStartsAt(string $lessonDate, ?string $lessonStartTime): CarbonImmutable
    {
        $date = CarbonImmutable::parse($lessonDate)->toDateString();
        $time = $lessonStartTime ? CarbonImmutable::parse($lessonStartTime)->format('H:i') : '00:00';

        return CarbonImmutable::parse("{$date} {$time}", self::timezone())
            ->setTimezone(config('app.timezone'));
    }

    private static function atLeastMinimum(CarbonImmutable $deadline): CarbonImmutable
    {
        $minimum = self::now()->addMinutes((int) config('booking_payments.minimum_hold_minutes', 15));

        return $deadline->lessThan($minimum) ? $minimum : $deadline;
    }

    private static function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
