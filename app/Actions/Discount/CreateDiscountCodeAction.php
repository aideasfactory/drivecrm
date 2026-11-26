<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Enums\DiscountType;
use App\Models\DiscountCode;
use Illuminate\Support\Facades\Log;

class CreateDiscountCodeAction
{
    /**
     * @param  array{code: string, label: string, type: string, percentage?: int|null, amount?: float|int|string|null}  $data
     */
    public function __invoke(array $data): DiscountCode
    {
        $type = DiscountType::from($data['type']);

        $discountCode = DiscountCode::create([
            'code' => DiscountCode::normaliseCode($data['code']),
            'label' => $data['label'],
            'type' => $type,
            'percentage' => $type === DiscountType::PERCENTAGE ? (int) $data['percentage'] : null,
            'amount_pence' => $type === DiscountType::AMOUNT ? (int) round(((float) $data['amount']) * 100) : null,
            'active' => true,
        ]);

        Log::info('Discount code created', [
            'discount_code_id' => $discountCode->id,
            'code' => $discountCode->code,
            'label' => $discountCode->label,
            'type' => $type->value,
            'percentage' => $discountCode->percentage,
            'amount_pence' => $discountCode->amount_pence,
        ]);

        return $discountCode;
    }
}
