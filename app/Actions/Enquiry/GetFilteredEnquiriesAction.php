<?php

declare(strict_types=1);

namespace App\Actions\Enquiry;

use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GetFilteredEnquiriesAction
{
    /**
     * Status buckets are keyed off max_step_reached alone:
     * 2 = completed enquiry (2-step booking flow), 6 = full onboarding
     * (legacy 6-step flow ending in payment), anything else = in progress.
     *
     * @param  array{status: string, area: string, date_from: ?string, date_to: ?string, q: ?string}  $filters
     * @return Builder<Enquiry>
     */
    public function __invoke(array $filters): Builder
    {
        $status = $filters['status'];
        $area = $filters['area'];
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $search = $filters['q'];

        return Enquiry::query()
            ->when($status === 'completed', function (Builder $query): void {
                $query->where('max_step_reached', 2);
            })
            ->when($status === 'full_onboarding', function (Builder $query): void {
                $query->where('max_step_reached', 6);
            })
            ->when($status === 'in_progress', function (Builder $query): void {
                $query->whereNotIn('max_step_reached', [2, 6]);
            })
            ->when($area === 'in_area', function (Builder $query): void {
                $query->where('data->steps->step2->in_area', true);
            })
            ->when($area === 'out_of_area', function (Builder $query): void {
                $query->where('data->steps->step2->in_area', false);
            })
            ->when($area === 'unknown', function (Builder $query): void {
                $query->whereNull('data->steps->step2->in_area');
            })
            ->when($dateFrom, function (Builder $query, string $from): void {
                $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            })
            ->when($dateTo, function (Builder $query, string $to): void {
                $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            })
            ->when($search, function (Builder $query, string $term): void {
                $like = '%'.$term.'%';

                $query->where(function (Builder $nested) use ($like): void {
                    $nested->where('data->steps->step1->first_name', 'like', $like)
                        ->orWhere('data->steps->step1->last_name', 'like', $like)
                        ->orWhere('data->steps->step1->email', 'like', $like)
                        ->orWhere('data->steps->step1->phone', 'like', $like)
                        ->orWhere('data->steps->step1->postcode', 'like', $like)
                        ->orWhereRaw(
                            "CONCAT_WS(' ', JSON_UNQUOTE(JSON_EXTRACT(data, '$.steps.step1.first_name')), JSON_UNQUOTE(JSON_EXTRACT(data, '$.steps.step1.last_name'))) LIKE ?",
                            [$like]
                        );
                });
            });
    }
}
