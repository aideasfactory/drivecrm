<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackagePricingResource extends JsonResource
{
    /**
     * Transform the pricing breakdown into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array $pricing */
        $pricing = $this->resource;

        return [
            'package_price_pence' => $pricing['package_price_pence'],
            'package_price' => $pricing['package_price'],
            'booking_fee' => $pricing['booking_fee'],
            'digital_fee_per_lesson' => $pricing['digital_fee_per_lesson'],
            'digital_fee_total' => $pricing['digital_fee_total'],
            'lessons_count' => $pricing['lessons_count'],
            'promo_code' => $pricing['promo_code'],
            'promo_discount' => $pricing['promo_discount'],
            'subtotal' => $pricing['subtotal'],
            'total' => $pricing['total'],
            'total_pence' => $pricing['total_pence'],
            'weekly_payment' => $pricing['weekly_payment'],
        ];
    }
}
