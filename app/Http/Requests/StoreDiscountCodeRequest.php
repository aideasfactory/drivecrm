<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DiscountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Codes are stored upper case, so uniqueness is checked on the upper
     * case value.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:32', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('discount_codes', 'code')],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DiscountType::class)],
            'percentage' => ['nullable', 'required_if:type,'.DiscountType::PERCENTAGE->value, 'integer', 'min:1', 'max:100'],
            'amount' => ['nullable', 'required_if:type,'.DiscountType::AMOUNT->value, 'numeric', 'min:0.01', 'max:10000', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'A code is required, e.g. FEEDBACK10.',
            'code.regex' => 'Codes can only use letters, numbers, dashes and underscores (no spaces).',
            'code.unique' => 'That code already exists. Codes are never deleted, so pick a new one.',
            'label.required' => 'A label is required for the discount code.',
            'percentage.required_if' => 'A discount percentage is required.',
            'percentage.integer' => 'The discount percentage must be a whole number.',
            'percentage.min' => 'The discount percentage must be at least 1.',
            'percentage.max' => 'The discount percentage cannot exceed 100.',
            'amount.required_if' => 'A discount amount is required.',
            'amount.min' => 'The discount amount must be at least £0.01.',
            'amount.decimal' => 'The discount amount can have at most 2 decimal places.',
        ];
    }
}
