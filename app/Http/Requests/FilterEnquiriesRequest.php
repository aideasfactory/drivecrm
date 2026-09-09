<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterEnquiriesRequest extends FormRequest
{
    private const STATUSES = ['all', 'completed', 'full_onboarding', 'in_progress'];

    private const AREAS = ['all', 'in_area', 'out_of_area', 'unknown'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $status = $this->query('status', 'all');
        $area = $this->query('area', 'all');

        $this->merge([
            'status' => in_array($status, self::STATUSES, true) ? $status : 'all',
            'area' => in_array($area, self::AREAS, true) ? $area : 'all',
            'date_from' => $this->filled('date_from') ? $this->query('date_from') : null,
            'date_to' => $this->filled('date_to') ? $this->query('date_to') : null,
            'q' => $this->filled('q') ? trim((string) $this->query('q')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:'.implode(',', self::STATUSES)],
            'area' => ['nullable', 'in:'.implode(',', self::AREAS)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_from.date_format' => 'The start date must be a valid date.',
            'date_to.date_format' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be on or after the start date.',
            'q.max' => 'The search query may not be longer than 255 characters.',
        ];
    }

    /**
     * @return array{status: string, area: string, date_from: ?string, date_to: ?string, q: ?string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? 'all',
            'area' => $validated['area'] ?? 'all',
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'q' => filled($validated['q'] ?? null) ? $validated['q'] : null,
        ];
    }
}
