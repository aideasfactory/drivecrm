<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use Illuminate\Support\Facades\Log;

class SetDiscountCodeActiveAction
{
    /**
     * Switch a code on or off. Codes are never deleted, so the orders that
     * used one can always be traced back to it.
     */
    public function __invoke(DiscountCode $discountCode, bool $active): DiscountCode
    {
        $discountCode->update(['active' => $active]);

        Log::info($active ? 'Discount code activated' : 'Discount code deactivated', [
            'discount_code_id' => $discountCode->id,
            'code' => $discountCode->code,
            'label' => $discountCode->label,
        ]);

        return $discountCode;
    }
}
