<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDiscountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'percentage' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'A label is required for the discount code.',
            'percentage.required' => 'A discount percentage is required.',
            'percentage.integer' => 'The discount percentage must be a whole number.',
            'percentage.min' => 'The discount percentage must be at least 1.',
            'percentage.max' => 'The discount percentage cannot exceed 100.',
        ];
    }
}
