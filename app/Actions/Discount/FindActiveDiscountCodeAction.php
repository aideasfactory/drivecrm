<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;

class FindActiveDiscountCodeAction
{
    /**
     * Find an active code by what the learner typed (case-insensitive).
     */
    public function __invoke(string $code): ?DiscountCode
    {
        $code = DiscountCode::normaliseCode($code);

        if ($code === '') {
            return null;
        }

        return DiscountCode::query()
            ->active()
            ->byCode($code)
            ->first();
    }
}
