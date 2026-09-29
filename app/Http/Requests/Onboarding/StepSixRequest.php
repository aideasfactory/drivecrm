<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepSixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['required', 'string', 'in:upfront,weekly'],
            'test_pass_guarantee' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'test_pass_guarantee.boolean' => 'Please choose whether to add Pass Your Test Guarantee.',
        ];
    }
}
