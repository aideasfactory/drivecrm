<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\Concerns\ValidatesDiaryDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GetCalendarItemsRequest extends FormRequest
{
    use ValidatesDiaryDateRange;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Either a single `date` (day view) or an inclusive `from` / `to` range
     * (week view). When `date` is present the range is ignored.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required_without_all:from,to', 'date', 'date_format:Y-m-d'],
            ...$this->dateRangeRules(required: false, extraRules: ['exclude_with:date']),
            'available_only' => ['sometimes', 'boolean'],
            'exclude_drafts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->dateRangeMessages();
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateDateRangeSpan($validator);
    }

    /**
     * Whether this is a range (week view) request rather than a single day.
     */
    public function isRange(): bool
    {
        return ! $this->filled('date');
    }
}
