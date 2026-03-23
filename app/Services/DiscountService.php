<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Discount\CreateDiscountCodeAction;
use App\Actions\Discount\DeleteDiscountCodeAction;
use App\Actions\Discount\GetAllDiscountCodesAction;
use App\Models\DiscountCode;
use Illuminate\Support\Collection;

class DiscountService extends BaseService
{
    public function __construct(
        protected GetAllDiscountCodesAction $getAllDiscountCodes,
        protected CreateDiscountCodeAction $createDiscountCode,
        protected DeleteDiscountCodeAction $deleteDiscountCode
    ) {}

    /**
     * @return Collection<int, DiscountCode>
     */
    public function getAll(): Collection
    {
        return ($this->getAllDiscountCodes)();
    }

    public function create(array $data): DiscountCode
    {
        return ($this->createDiscountCode)($data);
    }

    public function delete(DiscountCode $discountCode): void
    {
        ($this->deleteDiscountCode)($discountCode);
    }

    /**
     * Find an active discount code by UUID.
     */
    public function findActiveByUuid(string $uuid): ?DiscountCode
    {
        return DiscountCode::query()
            ->where('id', $uuid)
            ->where('active', true)
            ->first();
    }
}
