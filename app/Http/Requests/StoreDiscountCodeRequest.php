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
            'percentage' => ['required', 'integer', 'in:5,10,15,20'],
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
            'percentage.in' => 'The discount percentage must be 5, 10, 15, or 20.',
        ];
    }
}
