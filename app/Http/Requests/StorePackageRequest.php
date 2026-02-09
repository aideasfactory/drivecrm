<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'total_price_pence' => ['required', 'integer', 'min:0'],
            'lessons_count' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required',
            'total_price_pence.required' => 'Total price is required',
            'total_price_pence.integer' => 'Price must be a valid number in pence',
            'total_price_pence.min' => 'Price must be at least 0',
            'lessons_count.required' => 'Number of lessons is required',
            'lessons_count.min' => 'Must have at least 1 lesson',
        ];
    }
}
