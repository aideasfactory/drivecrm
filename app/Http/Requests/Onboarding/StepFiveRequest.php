<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepFiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'pickup_address_line_1' => ['nullable', 'string', 'max:255'],
            'pickup_address_line_2' => ['nullable', 'string', 'max:255'],
            'pickup_city' => ['nullable', 'string', 'max:100'],
            'pickup_postcode' => ['nullable', 'string', 'max:20'],
            'promo_code' => ['nullable', 'string', 'max:50'],
            'booking_for_someone_else' => ['nullable', 'boolean'],
            // Always allow learner fields to be submitted (they'll be validated based on booking_for_someone_else)
            'learner_first_name' => ['nullable', 'string', 'max:100'],
            'learner_last_name' => ['nullable', 'string', 'max:100'],
            'learner_email' => ['nullable', 'email', 'max:255'],
            'learner_phone' => ['nullable', 'string', 'min:10', 'max:20'],
            'learner_dob' => ['nullable', 'date', 'before:today'],
        ];

        // Make fields required if booking for someone else
        if ($this->input('booking_for_someone_else')) {
            $rules['learner_first_name'] = ['required', 'string', 'max:100'];
            $rules['learner_last_name'] = ['required', 'string', 'max:100'];
            $rules['learner_email'] = ['required', 'email', 'max:255'];
            $rules['learner_phone'] = ['required', 'string', 'min:10', 'max:20'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'learner_first_name.required' => 'Learner first name is required.',
            'learner_last_name.required' => 'Learner last name is required.',
            'learner_email.required' => 'Learner email is required.',
            'learner_email.email' => 'Please provide a valid email address.',
            'learner_phone.required' => 'Learner phone number is required.',
        ];
    }
}
