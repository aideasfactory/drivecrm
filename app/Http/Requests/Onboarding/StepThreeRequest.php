<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepThreeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'exists:packages,id'],
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
