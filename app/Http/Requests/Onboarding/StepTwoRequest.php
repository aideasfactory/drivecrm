<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StepTwoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only instructors who can be paid (Stripe account + payouts enabled),
            // matching the instructor list shown on this step.
            'instructor_id' => [
                'required',
                Rule::exists('instructors', 'id')
                    ->where('status', 'active')
                    ->where('payouts_enabled', true)
                    ->whereNotNull('stripe_account_id'),
            ],
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
