<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use App\Models\Enquiry;
use App\Support\Discounts;
use Illuminate\Support\Facades\Log;

class ApplyDiscountToEnquiryAction
{
    /**
     * Put a discount code on an onboarding enquiry. One code per booking:
     * it replaces any code (or discount link) already applied.
     */
    public function __invoke(Enquiry $enquiry, DiscountCode $discountCode): Enquiry
    {
        $data = $enquiry->data ?? [];
        $previous = $data['discount'] ?? null;
        $data['discount'] = Discounts::forEnquiry($discountCode);

        $enquiry->data = $data;
        $enquiry->save();

        Log::info('Discount code applied to enquiry', [
            'enquiry_id' => $enquiry->id,
            'discount_code_id' => $discountCode->id,
            'code' => $discountCode->code,
            'replaced_discount_code_id' => $previous['id'] ?? null,
        ]);

        return $enquiry;
    }
}
