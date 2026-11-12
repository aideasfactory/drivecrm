<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Enquiry;
use App\Models\Package;
use Carbon\Carbon;

/**
 * Pricing rules for the Pass Your Test Guarantee sold on the public booking
 * form. It is always charged at `price()` and never included free:
 *  - packages of `mandatoryMinimumLessons()` or more lessons always include it
 *    (no opt-out, shown in the package price);
 *  - smaller packages can opt in on the payment step.
 * Weekly payers pay it with their first instalment. Values come from
 * config/test_pass_guarantee.php.
 *
 * Orders created before 2026-11-12 may still carry a guarantee included free
 * (`includes_test_pass_guarantee` with `test_pass_guarantee_pence = 0`).
 */
final class TestPassGuarantee
{
    public static function price(): float
    {
        return (float) config('test_pass_guarantee.price', 50);
    }

    public static function pricePence(): int
    {
        return (int) round(self::price() * 100);
    }

    /**
     * Packages with at least this many lessons always include the guarantee.
     */
    public static function mandatoryMinimumLessons(): int
    {
        return (int) config('test_pass_guarantee.mandatory_minimum_lessons', 5);
    }

    public static function isMandatoryForPackage(Package $package): bool
    {
        return (int) $package->lessons_count >= self::mandatoryMinimumLessons();
    }

    public static function termsUrl(): string
    {
        return (string) config('test_pass_guarantee.terms_url');
    }

    /**
     * Total driving hours booked: every lesson in the order uses the same slot
     * length chosen at the date/time step. Falls back to one hour per lesson
     * when the slot times are missing or unparseable.
     */
    public static function bookedHours(int $lessonsCount, ?string $startTime, ?string $endTime): float
    {
        $lessonsCount = max(0, $lessonsCount);
        $minutesPerLesson = 0;

        if ($startTime && $endTime) {
            try {
                $minutesPerLesson = (int) Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime), false);
            } catch (\Throwable) {
                $minutesPerLesson = 0;
            }
        }

        if ($minutesPerLesson <= 0) {
            return (float) $lessonsCount;
        }

        return round($lessonsCount * $minutesPerLesson / 60, 2);
    }

    /**
     * Resolve whether the order includes the guarantee and how much to charge:
     * the full price when it is mandatory for the package or the learner opted
     * in, nothing otherwise.
     *
     * @return array{included: bool, charge_pence: int}
     */
    public static function resolve(bool $mandatory, bool $optedIn): array
    {
        return $mandatory || $optedIn
            ? ['included' => true, 'charge_pence' => self::pricePence()]
            : ['included' => false, 'charge_pence' => 0];
    }

    /**
     * Whether the learner ticked the add-on on the payment step.
     */
    public static function optedInForEnquiry(Enquiry $enquiry): bool
    {
        $step6 = $enquiry->getStepData(6) ?? [];

        return (bool) ($step6['test_pass_guarantee'] ?? false);
    }

    /**
     * Booking-form view data shared by the summary and payment steps.
     *
     * @return array{mandatory: bool, opted_in: bool, price: string, price_pence: int, mandatory_minimum_lessons: int, terms_url: string}
     */
    public static function bookingFormData(Enquiry $enquiry, Package $package): array
    {
        $mandatory = self::isMandatoryForPackage($package);

        return [
            'mandatory' => $mandatory,
            'opted_in' => $mandatory || self::optedInForEnquiry($enquiry),
            'price' => number_format(self::price(), 2),
            'price_pence' => self::pricePence(),
            'mandatory_minimum_lessons' => self::mandatoryMinimumLessons(),
            'terms_url' => self::termsUrl(),
        ];
    }

    /**
     * @return array{included: bool, charge_pence: int}
     */
    public static function resolveForEnquiry(Enquiry $enquiry, Package $package): array
    {
        return self::resolve(self::isMandatoryForPackage($package), self::optedInForEnquiry($enquiry));
    }

    /**
     * The guarantee charge already in a package's displayed price on the
     * package list (mandatory packages only).
     */
    public static function includedInPackagePricePence(Package $package): int
    {
        return self::isMandatoryForPackage($package) ? self::pricePence() : 0;
    }
}
