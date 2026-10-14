<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;
use App\Support\Fees;

class CalculatePackagePricingAction
{
    /**
     * Calculate the full pricing breakdown for a package.
     *
     * @param  array{code?: string, percentage?: int|float}|null  $promoDiscount
     * @return array{
     *   package_price_pence: int,
     *   package_price: float,
     *   booking_fee: float,
     *   digital_fee_per_hour: float,
     *   digital_fee_per_lesson: float,
     *   digital_fee_total: float,
     *   hours: float,
     *   lessons_count: int,
     *   promo_code: string|null,
     *   promo_discount: float,
     *   subtotal: float,
     *   total: float,
     *   total_pence: int,
     *   weekly_payment: float,
     * }
     */
    public function __invoke(Package $package, ?array $promoDiscount = null, ?string $startTime = null, ?string $endTime = null): array
    {
        $packagePrice = $package->total_price_pence / 100;
        $bookingFee = Fees::bookingFee();
        $lessonsCount = (int) $package->lessons_count;
        $digitalFeePerHour = Fees::digitalFeePerHour();
        $digitalFeeTotal = Fees::digitalFeeTotal($lessonsCount, $startTime, $endTime);
        $digitalFeePerLesson = $lessonsCount > 0
            ? round(Fees::digitalFeeTotalPence(1, $startTime, $endTime) / 100, 2)
            : 0.0;

        $subtotal = $packagePrice + $bookingFee + $digitalFeeTotal;

        $promoAmount = 0.0;
        $promoCode = null;

        if ($promoDiscount && isset($promoDiscount['percentage'])) {
            $promoAmount = round($packagePrice * ($promoDiscount['percentage'] / 100), 2);
            $promoCode = $promoDiscount['code'] ?? null;
        }

        $total = round($subtotal - $promoAmount, 2);
        $weeklyPayment = $lessonsCount > 0
            ? round($total / $lessonsCount, 2)
            : 0.0;

        return [
            'package_price_pence' => $package->total_price_pence,
            'package_price' => round($packagePrice, 2),
            'booking_fee' => $bookingFee,
            'digital_fee_per_hour' => $digitalFeePerHour,
            'digital_fee_per_lesson' => $digitalFeePerLesson,
            'digital_fee_total' => $digitalFeeTotal,
            'hours' => Fees::bookedHours($lessonsCount, $startTime, $endTime),
            'lessons_count' => $lessonsCount,
            'promo_code' => $promoCode,
            'promo_discount' => $promoAmount,
            'subtotal' => round($subtotal, 2),
            'total' => $total,
            'total_pence' => (int) round($total * 100),
            'weekly_payment' => $weeklyPayment,
        ];
    }
}
