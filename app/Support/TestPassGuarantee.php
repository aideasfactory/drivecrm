<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Enquiry;
use Carbon\Carbon;

/**
 * Pricing rules for the Pass Your Test Guarantee add-on sold on the public
 * booking form. It is always optional and always charged at `price()`,
 * whatever the package size or payment mode. Values come from
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
     * the full price when the learner opted in, nothing otherwise.
     *
     * @return array{included: bool, charge_pence: int}
     */
    public static function resolve(bool $optedIn): array
    {
        return $optedIn
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
     * @return array{opted_in: bool, price: string, price_pence: int, terms_url: string}
     */
    public static function bookingFormData(Enquiry $enquiry): array
    {
        return [
            'opted_in' => self::optedInForEnquiry($enquiry),
            'price' => number_format(self::price(), 2),
            'price_pence' => self::pricePence(),
            'terms_url' => self::termsUrl(),
        ];
    }

    /**
     * @return array{included: bool, charge_pence: int}
     */
    public static function resolveForEnquiry(Enquiry $enquiry): array
    {
        return self::resolve(self::optedInForEnquiry($enquiry));
    }
}
