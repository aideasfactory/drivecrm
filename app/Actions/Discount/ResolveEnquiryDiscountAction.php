<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use App\Models\Enquiry;
use App\Support\Discounts;

class ResolveEnquiryDiscountAction
{
    /**
     * Re-check the enquiry's discount against Mission Control just before
     * the order is created: the code may have been switched off since it
     * was applied. Returns a fresh snapshot of the code, or null with
     * `valid` false when the code is no longer usable.
     *
     * @return array{valid: bool, discount: ?array<string, mixed>}
     */
    public function __invoke(Enquiry $enquiry): array
    {
        $discount = $enquiry->getDiscountData();

        if ($discount === null) {
            return ['valid' => true, 'discount' => null];
        }

        $discountCode = isset($discount['id'])
            ? DiscountCode::query()->active()->find($discount['id'])
            : null;

        if ($discountCode === null) {
            return ['valid' => false, 'discount' => null];
        }

        return ['valid' => true, 'discount' => Discounts::forEnquiry($discountCode)];
    }
}
