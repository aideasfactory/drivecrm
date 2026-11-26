<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DiscountType;
use App\Models\DiscountCode;

/**
 * Single source of truth for discount code amounts on onboarding bookings.
 *
 * A discount always comes off the package price (which already carries any
 * instructor price uplift), never off the booking or digital fee. Both the
 * step 3–6 price preview and order creation work the amount out here, so
 * what the learner is shown is what Stripe charges.
 *
 * The enquiry stores the discount as an array snapshot (see forEnquiry()).
 * Enquiries started before typed codes carry the older
 * {id, label, percentage} shape, which is read as a percentage discount.
 */
final class Discounts
{
    /**
     * Enquiry snapshot of a discount code.
     *
     * @return array{id: string, code: ?string, label: string, type: string, percentage: ?int, amount_pence: ?int, summary: string}
     */
    public static function forEnquiry(DiscountCode $discountCode): array
    {
        $discount = [
            'id' => $discountCode->id,
            'code' => $discountCode->code,
            'label' => $discountCode->label,
            'type' => $discountCode->type->value,
            'percentage' => $discountCode->percentage,
            'amount_pence' => $discountCode->amount_pence,
        ];

        return [...$discount, 'summary' => self::summary($discount)];
    }

    /**
     * Pence taken off the package price, capped at the package price.
     *
     * @param  array{type?: string, percentage?: int|float|null, amount_pence?: int|null}|null  $discount
     */
    public static function discountPence(?array $discount, int $packagePricePence): int
    {
        if ($discount === null || $packagePricePence <= 0) {
            return 0;
        }

        if (self::type($discount) === DiscountType::AMOUNT) {
            return max(0, min((int) ($discount['amount_pence'] ?? 0), $packagePricePence));
        }

        $percentage = (float) ($discount['percentage'] ?? 0);

        if ($percentage <= 0) {
            return 0;
        }

        // Same rounding order creation has always used: round the discounted
        // price, the discount is whatever was taken off.
        $discountedPence = (int) round($packagePricePence * (1 - min($percentage, 100) / 100));

        return $packagePricePence - $discountedPence;
    }

    /**
     * Short description for the learner, e.g. "10% off" or "£10 off".
     *
     * @param  array{type?: string, percentage?: int|float|null, amount_pence?: int|null}  $discount
     */
    public static function summary(array $discount): string
    {
        if (self::type($discount) === DiscountType::AMOUNT) {
            $pounds = ((int) ($discount['amount_pence'] ?? 0)) / 100;

            return '£'.(fmod($pounds, 1.0) === 0.0 ? number_format($pounds) : number_format($pounds, 2)).' off';
        }

        return ((int) ($discount['percentage'] ?? 0)).'% off';
    }

    /**
     * What is recorded on the order and shown in the CRM: the typed code,
     * or the label for link-only codes that have no code.
     *
     * @param  array{code?: ?string, label?: ?string}  $discount
     */
    public static function displayCode(array $discount): ?string
    {
        $code = $discount['code'] ?? null;

        return $code !== null && $code !== '' ? $code : ($discount['label'] ?? null);
    }

    /**
     * Add the learner-facing summary to an enquiry discount (older snapshots
     * don't carry one).
     *
     * @param  array<string, mixed>|null  $discount
     * @return array<string, mixed>|null
     */
    public static function withSummary(?array $discount): ?array
    {
        if ($discount === null) {
            return null;
        }

        return [...$discount, 'summary' => self::summary($discount)];
    }

    /**
     * @param  array{type?: string}  $discount
     */
    protected static function type(array $discount): DiscountType
    {
        return DiscountType::tryFrom((string) ($discount['type'] ?? '')) ?? DiscountType::PERCENTAGE;
    }
}
