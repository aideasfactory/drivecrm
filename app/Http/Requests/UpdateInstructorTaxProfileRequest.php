<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateInstructorTaxProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->instructor !== null;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['vrn', 'utr', 'nino', 'companies_house_number'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $payload[$field] = strtoupper(str_replace(' ', '', $value));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $instructorId = $this->user()?->instructor?->id;

        return [
            'business_type' => ['required', new Enum(BusinessType::class)],
            'vat_registered' => ['required', 'boolean'],
            'vrn' => [
                'nullable',
                'required_if:vat_registered,true',
                'string',
                'regex:/^\d{9}$/',
                Rule::unique('instructors', 'vrn')->ignore($instructorId),
            ],
            'utr' => [
                'nullable',
                'required_if:business_type,sole_trader,partnership',
                'string',
                'regex:/^\d{10}$/',
            ],
            'nino' => [
                'nullable',
                'required_if:business_type,sole_trader,partnership',
                'string',
                'regex:/^[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z]\d{6}[A-D]$/',
            ],
            'companies_house_number' => [
                'nullable',
                'required_if:business_type,limited_company',
                'string',
                'regex:/^[A-Z0-9]{8}$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_type.required' => 'Please select your business type.',
            'vrn.required_if' => 'A VAT registration number is required when VAT-registered is on.',
            'vrn.regex' => 'VRN must be exactly 9 digits.',
            'vrn.unique' => 'That VRN is already linked to another instructor account.',
            'utr.required_if' => 'A UTR is required for sole traders and partnerships.',
            'utr.regex' => 'UTR must be exactly 10 digits.',
            'nino.required_if' => 'A National Insurance number is required for sole traders and partnerships.',
            'nino.regex' => 'That National Insurance number is not in a valid format (e.g. AB123456C).',
            'companies_house_number.required_if' => 'A Companies House number is required for a limited company.',
            'companies_house_number.regex' => 'Companies House number must be 8 characters (letters and digits).',
        ];
    }
}
