<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class DiaryDateSpan implements ValidationRule
{
    public function __construct(private string $fromField = 'from') {}

    /**
     * Inclusive diary ranges are capped at 31 days (`to - from` <= 30).
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $from = request()->input($this->fromField);

        if (! is_string($from) || ! is_string($value)) {
            return;
        }

        try {
            $fromDate = Carbon::createFromFormat('!Y-m-d', $from);
            $toDate = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return;
        }

        if ($fromDate === false || $toDate === false) {
            return;
        }

        if ($fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $value) {
            return;
        }

        if ($toDate->lt($fromDate)) {
            return;
        }

        if ($fromDate->diffInDays($toDate) > 30) {
            $fail('The date range may not exceed 31 days.');
        }
    }
}
