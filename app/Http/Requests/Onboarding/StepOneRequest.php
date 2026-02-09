<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepOneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+44\s?7\d{3}|\(?07\d{3}\)?)\s?\d{3}\s?\d{3}$/'],
            'postcode' => ['required', 'string', 'regex:/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i'],
            'privacy_consent' => ['required', 'accepted'],
            'booking_for_other' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name',
            'last_name.required' => 'Please enter your last name',
            'email.required' => 'Please enter your email address',
            'email.email' => 'Please enter a valid email address',
            'phone.required' => 'Please enter your phone number',
            'phone.regex' => 'Please enter a valid UK mobile number',
            'postcode.required' => 'Please enter your postcode',
            'postcode.regex' => 'Please enter a valid UK postcode',
            'privacy_consent.required' => 'You must agree to the Terms & Conditions to continue',
            'privacy_consent.accepted' => 'You must agree to the Terms & Conditions to continue',
        ];
    }
}
