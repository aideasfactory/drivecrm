<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\DiaryDateSpan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetInstructorCalendarItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalised = [];

        foreach (['available_only', 'exclude_drafts'] as $field) {
            if ($this->exists($field)) {
                $normalised[$field] = $this->boolean($field);
            }
        }

        if ($normalised !== []) {
            $this->merge($normalised);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required_without_all:from,to', 'nullable', 'date', 'date_format:Y-m-d'],
            'from' => [
                Rule::excludeIf(fn (): bool => $this->filled('date')),
                'required_with:to',
                'nullable',
                'date',
                'date_format:Y-m-d',
            ],
            'to' => [
                Rule::excludeIf(fn (): bool => $this->filled('date')),
                'required_with:from',
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:from',
                new DiaryDateSpan,
            ],
            'available_only' => ['sometimes', 'boolean'],
            'exclude_drafts' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The to date must be on or after from.',
            'from.required_with' => 'The from field is required when to is present.',
            'to.required_with' => 'The to field is required when from is present.',
        ];
    }
}
