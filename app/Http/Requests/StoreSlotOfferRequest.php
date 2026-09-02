<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSlotOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:1000'],
            'package_id' => ['required_without:one_off_price_pence', 'prohibits:one_off_price_pence', 'nullable', 'integer', 'exists:packages,id'],
            'one_off_price_pence' => ['required_without:package_id', 'prohibits:package_id', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_id.required_without' => 'Select a package or enter a one-off price.',
            'one_off_price_pence.required_without' => 'Select a package or enter a one-off price.',
            'one_off_price_pence.min' => 'The one-off price must be at least 1 pence.',
            'message.max' => 'The message cannot exceed 1000 characters.',
        ];
    }
}
