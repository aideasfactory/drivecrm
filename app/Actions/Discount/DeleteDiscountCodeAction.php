<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use Illuminate\Support\Facades\Log;

class DeleteDiscountCodeAction
{
    public function __invoke(DiscountCode $discountCode): void
    {
        Log::info('Discount code deleted', [
            'discount_code_id' => $discountCode->id,
            'label' => $discountCode->label,
            'percentage' => $discountCode->percentage,
        ]);

        $discountCode->delete();
    }
}
