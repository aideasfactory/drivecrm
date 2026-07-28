<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Enquiry\GetFilteredEnquiriesAction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EnquiryService extends BaseService
{
    public function __construct(
        protected GetFilteredEnquiriesAction $getFilteredEnquiries,
    ) {}

    public function getFiltered(string $status, string $area): LengthAwarePaginator
    {
        return ($this->getFilteredEnquiries)($status, $area);
    }
}
