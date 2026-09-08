<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Enquiry\GetFilteredEnquiriesAction;
use App\Models\Enquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class EnquiryService extends BaseService
{
    public function __construct(
        protected GetFilteredEnquiriesAction $getFilteredEnquiries,
    ) {}

    /**
     * @param  array{status: string, area: string, date_from: ?string, date_to: ?string, q: ?string}  $filters
     */
    public function getFiltered(array $filters): LengthAwarePaginator
    {
        return ($this->getFilteredEnquiries)($filters)
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * @param  array{status: string, area: string, date_from: ?string, date_to: ?string, q: ?string}  $filters
     * @return LazyCollection<string, Enquiry>
     */
    public function getFilteredForExport(array $filters): LazyCollection
    {
        return ($this->getFilteredEnquiries)($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursor();
    }
}
