<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Discount\ApplyDiscountToEnquiryAction;
use App\Actions\Discount\CreateDiscountCodeAction;
use App\Actions\Discount\FindActiveDiscountCodeAction;
use App\Actions\Discount\GetAllDiscountCodesAction;
use App\Actions\Discount\GetStudentDiscountedOrdersAction;
use App\Actions\Discount\RemoveDiscountFromEnquiryAction;
use App\Actions\Discount\ResolveEnquiryDiscountAction;
use App\Actions\Discount\SetDiscountCodeActiveAction;
use App\Models\DiscountCode;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Support\Collection;

class DiscountService extends BaseService
{
    public function __construct(
        protected GetAllDiscountCodesAction $getAllDiscountCodes,
        protected CreateDiscountCodeAction $createDiscountCode,
        protected SetDiscountCodeActiveAction $setDiscountCodeActive,
        protected FindActiveDiscountCodeAction $findActiveDiscountCode,
        protected ApplyDiscountToEnquiryAction $applyDiscountToEnquiry,
        protected RemoveDiscountFromEnquiryAction $removeDiscountFromEnquiry,
        protected ResolveEnquiryDiscountAction $resolveEnquiryDiscount,
        protected GetStudentDiscountedOrdersAction $getStudentDiscountedOrders,
    ) {}

    /**
     * @return Collection<int, DiscountCode>
     */
    public function getAll(): Collection
    {
        return ($this->getAllDiscountCodes)();
    }

    /**
     * @param  array{code: string, label: string, type: string, percentage?: int|null, amount?: float|int|string|null}  $data
     */
    public function create(array $data): DiscountCode
    {
        return ($this->createDiscountCode)($data);
    }

    /**
     * Switch a code on or off (codes are never deleted).
     */
    public function setActive(DiscountCode $discountCode, bool $active): DiscountCode
    {
        return ($this->setDiscountCodeActive)($discountCode, $active);
    }

    /**
     * Find an active discount code by UUID (discount links).
     */
    public function findActiveByUuid(string $uuid): ?DiscountCode
    {
        return DiscountCode::query()
            ->where('id', $uuid)
            ->where('active', true)
            ->first();
    }

    /**
     * Apply the code a learner typed at onboarding step 6. Returns false when
     * no active code matches, leaving any discount already applied in place.
     */
    public function applyCodeToEnquiry(Enquiry $enquiry, string $code): bool
    {
        $discountCode = ($this->findActiveDiscountCode)($code);

        if ($discountCode === null) {
            return false;
        }

        ($this->applyDiscountToEnquiry)($enquiry, $discountCode);

        return true;
    }

    /**
     * Take the discount off an enquiry.
     */
    public function removeFromEnquiry(Enquiry $enquiry): Enquiry
    {
        return ($this->removeDiscountFromEnquiry)($enquiry);
    }

    /**
     * Re-check an enquiry's discount just before its order is created.
     *
     * @return array{valid: bool, discount: ?array<string, mixed>}
     */
    public function resolveForOrder(Enquiry $enquiry): array
    {
        return ($this->resolveEnquiryDiscount)($enquiry);
    }

    /**
     * A pupil's bookings that had a discount code.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getStudentDiscountedOrders(Student $student): Collection
    {
        return ($this->getStudentDiscountedOrders)($student);
    }
}
