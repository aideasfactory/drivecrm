<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use Illuminate\Support\Collection;

class GetAllDiscountCodesAction
{
    /**
     * @return Collection<int, DiscountCode>
     */
    public function __invoke(): Collection
    {
        return DiscountCode::query()
            ->orderByDesc('created_at')
            ->get();
    }
}
