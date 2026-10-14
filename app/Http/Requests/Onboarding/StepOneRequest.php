<?php

namespace App\Http\Requests\Onboarding;

use App\Support\PersonDetails;
use Illuminate\Foundation\Http\FormRequest;

class StepOneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the phone into E.164 before validation: strip spaces, dashes
     * and brackets, and forgive a leading 0 typed after the +44 dial code
     * (e.g. "+44 07710 896753" → "+447710896753").
     */
    protected function prepareForValidation(): void
    {
        foreach (['first_name', 'last_name', 'email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        if (is_string($this->phone)) {
            $phone = preg_replace('/[\s\-().]/', '', $this->phone);
            $phone = preg_replace('/^\+440/', '+44', (string) $phone);

            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => PersonDetails::nameRules(),
            'last_name' => PersonDetails::nameRules(),
            'email' => PersonDetails::emailRules(),
            'phone' => ['required', 'string', 'regex:/^\+(?:447\d{9}|(?!44)[1-9]\d{6,14})$/'],
            'postcode' => ['required', 'string', 'regex:/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i'],
            'privacy_consent' => ['required', 'accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
            'booking_for_other' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Please enter your first name',
            'last_name.required' => 'Please enter your last name',
            'email.required' => 'Please enter your email address',
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens and apostrophes',
            'first_name.max' => 'First name must be 100 characters or fewer',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens and apostrophes',
            'last_name.max' => 'Last name must be 100 characters or fewer',
            'email.email' => 'Enter a valid email address, e.g. name@example.com',
            'email.regex' => 'Enter a valid email address, e.g. name@example.com',
            'email.max' => 'Email address must be 255 characters or fewer',
            'phone.required' => 'Please enter your mobile number',
            'phone.regex' => str_starts_with((string) $this->phone, '+44')
                ? 'Enter a valid UK mobile number starting 07 or +447, e.g. 07700 900123'
                : 'Enter a valid mobile number for the selected country',
            'postcode.required' => 'Please enter your postcode',
            'postcode.regex' => 'Please enter a valid UK postcode',
            'privacy_consent.required' => 'You must agree to the Terms of Service and Privacy Policy to continue',
            'privacy_consent.accepted' => 'You must agree to the Terms of Service and Privacy Policy to continue',
        ];
    }
}
