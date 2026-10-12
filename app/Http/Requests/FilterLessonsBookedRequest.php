<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterLessonsBookedRequest extends FormRequest
{
    private const PAID = ['all', 'paid', 'unpaid'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paid = $this->query('paid', 'all');

        $this->merge([
            'paid' => in_array($paid, self::PAID, true) ? $paid : 'all',
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'paid' => ['nullable', 'in:'.implode(',', self::PAID)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paid.in' => 'Choose all, paid, or unpaid.',
        ];
    }

    public function paidStatus(): string
    {
        return $this->validated('paid') ?? 'all';
    }
}
