<?php

declare(strict_types=1);

namespace App\Actions\Discount;

use App\Models\DiscountCode;
use Illuminate\Support\Collection;

class GetAllDiscountCodesAction
{
    /**
     * Every code, newest first, with how many paid bookings used it
     * (`used_orders_count`).
     *
     * @return Collection<int, DiscountCode>
     */
    public function __invoke(): Collection
    {
        return DiscountCode::query()
            ->withCount('usedOrders')
            ->orderByDesc('created_at')
            ->get();
    }
}
