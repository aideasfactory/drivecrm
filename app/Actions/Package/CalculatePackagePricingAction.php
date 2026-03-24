<?php

declare(strict_types=1);

namespace App\Actions\Package;

use App\Models\Package;

class CalculatePackagePricingAction
{
    /**
     * Fee constants — single source of truth for all fee values.
     */
    public const BOOKING_FEE = 19.99;

    public const DIGITAL_FEE_PER_LESSON = 3.99;

    /**
     * Calculate the full pricing breakdown for a package.
     *
     * @param  array{code?: string, percentage?: int|float}|null  $promoDiscount
     * @return array{
     *   package_price_pence: int,
     *   package_price: float,
     *   booking_fee: float,
     *   digital_fee_per_lesson: float,
     *   digital_fee_total: float,
     *   lessons_count: int,
     *   promo_code: string|null,
     *   promo_discount: float,
     *   subtotal: float,
     *   total: float,
     *   total_pence: int,
     *   weekly_payment: float,
     * }
     */
    public function __invoke(Package $package, ?array $promoDiscount = null): array
    {
        $packagePrice = $package->total_price_pence / 100;
        $bookingFee = self::BOOKING_FEE;
        $digitalFeeTotal = self::DIGITAL_FEE_PER_LESSON * $package->lessons_count;

        $subtotal = $packagePrice + $bookingFee + $digitalFeeTotal;

        $promoAmount = 0.0;
        $promoCode = null;

        if ($promoDiscount && isset($promoDiscount['percentage'])) {
            $promoAmount = round($packagePrice * ($promoDiscount['percentage'] / 100), 2);
            $promoCode = $promoDiscount['code'] ?? null;
        }

        $total = round($subtotal - $promoAmount, 2);
        $weeklyPayment = $package->lessons_count > 0
            ? round($total / $package->lessons_count, 2)
            : 0.0;

        return [
            'package_price_pence' => $package->total_price_pence,
            'package_price' => round($packagePrice, 2),
            'booking_fee' => $bookingFee,
            'digital_fee_per_lesson' => self::DIGITAL_FEE_PER_LESSON,
            'digital_fee_total' => round($digitalFeeTotal, 2),
            'lessons_count' => $package->lessons_count,
            'promo_code' => $promoCode,
            'promo_discount' => $promoAmount,
            'subtotal' => round($subtotal, 2),
            'total' => $total,
            'total_pence' => (int) round($total * 100),
            'weekly_payment' => $weeklyPayment,
        ];
    }
}
