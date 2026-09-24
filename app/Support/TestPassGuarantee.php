<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentMode;
use App\Models\Enquiry;
use App\Models\Package;
use Carbon\Carbon;

/**
 * Pricing rules for the Pass Your Test Guarantee add-on sold on the public
 * booking form. Values come from config/test_pass_guarantee.php.
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

    public static function freeMinimumHours(): float
    {
        return (float) config('test_pass_guarantee.free_minimum_hours', 10);
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
     * Whether a booking of this size is large enough to get the guarantee free
     * (it still has to be paid in full).
     */
    public static function meetsFreeHoursThreshold(float $bookedHours): bool
    {
        return $bookedHours >= self::freeMinimumHours();
    }

    public static function qualifiesForFree(float $bookedHours, PaymentMode $paymentMode): bool
    {
        return $paymentMode === PaymentMode::UPFRONT && self::meetsFreeHoursThreshold($bookedHours);
    }

    /**
     * Resolve whether the order includes the guarantee and how much to charge.
     *
     * @return array{included: bool, charge_pence: int, is_free: bool}
     */
    public static function resolve(float $bookedHours, PaymentMode $paymentMode, bool $optedIn): array
    {
        if (self::qualifiesForFree($bookedHours, $paymentMode)) {
            return ['included' => true, 'charge_pence' => 0, 'is_free' => true];
        }

        if ($optedIn) {
            return ['included' => true, 'charge_pence' => self::pricePence(), 'is_free' => false];
        }

        return ['included' => false, 'charge_pence' => 0, 'is_free' => false];
    }

    /**
     * Hours booked through the booking form: package lessons × the slot length
     * picked at step 4.
     */
    public static function bookedHoursForEnquiry(Enquiry $enquiry, Package $package): float
    {
        $step4 = $enquiry->getStepData(4) ?? [];

        return self::bookedHours(
            (int) $package->lessons_count,
            $step4['start_time'] ?? null,
            $step4['end_time'] ?? null,
        );
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
     * @return array{opted_in: bool, price: string, price_pence: int, booked_hours: float, free_minimum_hours: float, free_when_paid_in_full: bool, terms_url: string, upfront: array{included: bool, charge_pence: int, is_free: bool}, weekly: array{included: bool, charge_pence: int, is_free: bool}}
     */
    public static function bookingFormData(Enquiry $enquiry, Package $package): array
    {
        $bookedHours = self::bookedHoursForEnquiry($enquiry, $package);
        $optedIn = self::optedInForEnquiry($enquiry);

        return [
            'opted_in' => $optedIn,
            'price' => number_format(self::price(), 2),
            'price_pence' => self::pricePence(),
            'booked_hours' => $bookedHours,
            'free_minimum_hours' => self::freeMinimumHours(),
            'free_when_paid_in_full' => self::meetsFreeHoursThreshold($bookedHours),
            'terms_url' => self::termsUrl(),
            'upfront' => self::resolve($bookedHours, PaymentMode::UPFRONT, $optedIn),
            'weekly' => self::resolve($bookedHours, PaymentMode::WEEKLY, $optedIn),
        ];
    }

    /**
     * @return array{included: bool, charge_pence: int, is_free: bool}
     */
    public static function resolveForEnquiry(Enquiry $enquiry, Package $package, PaymentMode $paymentMode): array
    {
        return self::resolve(
            self::bookedHoursForEnquiry($enquiry, $package),
            $paymentMode,
            self::optedInForEnquiry($enquiry),
        );
    }
}
