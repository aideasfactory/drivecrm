<?php

declare(strict_types=1);

namespace App\Actions\Enquiry;

use App\Models\Enquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetFilteredEnquiriesAction
{
    /**
     * Status buckets are keyed off max_step_reached alone:
     * 2 = completed enquiry (2-step booking flow), 6 = full onboarding
     * (legacy 6-step flow ending in payment), anything else = in progress.
     *
     * @param  string  $status  all|completed|full_onboarding|in_progress
     * @param  string  $area  all|in_area|out_of_area|unknown
     */
    public function __invoke(string $status, string $area, int $perPage = 25): LengthAwarePaginator
    {
        return Enquiry::query()
            ->when($status === 'completed', function ($query) {
                $query->where('max_step_reached', 2);
            })
            ->when($status === 'full_onboarding', function ($query) {
                $query->where('max_step_reached', 6);
            })
            ->when($status === 'in_progress', function ($query) {
                $query->whereNotIn('max_step_reached', [2, 6]);
            })
            ->when($area === 'in_area', function ($query) {
                $query->where('data->steps->step2->in_area', true);
            })
            ->when($area === 'out_of_area', function ($query) {
                $query->where('data->steps->step2->in_area', false);
            })
            ->when($area === 'unknown', function ($query) {
                $query->whereNull('data->steps->step2->in_area');
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
