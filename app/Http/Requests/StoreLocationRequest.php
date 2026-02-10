<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'postcode_sector' => [
                'required',
                'string',
                'regex:/^[A-Z]{1,2}[0-9]{1,2}$/',
                'max:4',
                Rule::unique('locations', 'postcode_sector')
                    ->where('instructor_id', $this->route('instructor')->id),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode_sector.required' => 'Postcode sector is required.',
            'postcode_sector.regex' => 'Postcode sector must be in valid format (e.g., TS7, WR14, M1).',
            'postcode_sector.unique' => 'This postcode sector is already added for this instructor.',
            'postcode_sector.max' => 'Postcode sector must not exceed 4 characters.',
        ];
    }
}
