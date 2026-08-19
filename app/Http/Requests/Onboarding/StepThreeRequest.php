<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StepThreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => [
                'required',
                // Onboarding only offers Drive packages — reject
                // instructor-owned or inactive packages outright.
                Rule::exists('packages', 'id')
                    ->whereNull('instructor_id')
                    ->where('active', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'package_id.required' => 'Please select a package to continue',
            'package_id.exists' => 'The selected package is not available',
        ];
    }
}
