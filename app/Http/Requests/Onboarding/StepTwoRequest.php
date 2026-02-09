<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepTwoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instructor_id' => ['required', 'exists:instructors,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'instructor_id.required' => 'Please select an instructor to continue',
            'instructor_id.exists' => 'The selected instructor is not available',
        ];
    }
}
