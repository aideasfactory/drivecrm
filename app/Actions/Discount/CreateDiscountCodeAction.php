<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use Illuminate\Support\Facades\Log;

class CreateDiscountCodeAction
{
    /**
     * @param  array{label: string, percentage: int}  $data
     */
    public function __invoke(array $data): DiscountCode
    {
        $discountCode = DiscountCode::create([
            'label' => $data['label'],
            'percentage' => $data['percentage'],
            'active' => true,
        ]);

        Log::info('Discount code created', [
            'discount_code_id' => $discountCode->id,
            'label' => $discountCode->label,
            'percentage' => $discountCode->percentage,
        ]);

        return $discountCode;
    }
}
