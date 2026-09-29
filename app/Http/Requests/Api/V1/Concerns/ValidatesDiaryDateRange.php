<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Shared `from` / `to` rules for the instructor diary range endpoints
 * (weekly view). Both dates are inclusive and must be sent together.
 */
trait ValidatesDiaryDateRange
{
    /**
     * Longest span a single range request may cover, in days (inclusive).
     */
    public const MAX_RANGE_DAYS = 31;

    /**
     * @param  array<int, string>  $extraRules  Rules prepended to both fields (e.g. `exclude_with:date`)
     * @return array<string, array<int, mixed>>
     */
    protected function dateRangeRules(bool $required, array $extraRules = []): array
    {
        $presence = $required ? 'required' : 'required_with:to';

        return [
            'from' => [...$extraRules, $presence, 'date_format:Y-m-d'],
            'to' => [...$extraRules, $required ? 'required' : 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function dateRangeMessages(): array
    {
        return [
            'from.required_with' => 'The from date is required when to is present.',
            'to.required_with' => 'The to date is required when from is present.',
            'to.after_or_equal' => 'The to date must be on or after from.',
        ];
    }

    /**
     * Reject ranges longer than MAX_RANGE_DAYS once the individual dates are valid.
     */
    protected function validateDateRangeSpan(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();

            if (! isset($data['from'], $data['to']) || isset($data['date'])) {
                return;
            }

            $days = (int) Carbon::parse($data['from'])->diffInDays(Carbon::parse($data['to'])) + 1;

            if ($days > self::MAX_RANGE_DAYS) {
                $validator->errors()->add('to', 'The range may not be longer than '.self::MAX_RANGE_DAYS.' days.');
            }
        });
    }
}
