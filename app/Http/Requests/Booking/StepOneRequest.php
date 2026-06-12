<?php

namespace App\Http\Requests\Booking;

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
        if (is_string($this->phone)) {
            $phone = preg_replace('/[\s\-().]/', '', $this->phone);
            $phone = preg_replace('/^\+440/', '+44', (string) $phone);

            $this->merge(['phone' => $phone]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^\+(?:447\d{9}|(?!44)[1-9]\d{6,14})$/'],
            'postcode' => ['required', 'string', 'regex:/^[A-Z]{1,2}[0-9]{1,2}[A-Z]?\s?[0-9][A-Z]{2}$/i'],
            'transmission' => ['required', 'in:manual,automatic,both'],
            'privacy_consent' => ['required', 'accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
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
            'phone.regex' => 'Please enter a valid mobile number including country code, e.g. +447710896753',
            'postcode.required' => 'Please enter your postcode',
            'postcode.regex' => 'Please enter a valid UK postcode',
            'transmission.required' => 'Please choose a transmission preference',
            'transmission.in' => 'Please choose a transmission preference',
            'privacy_consent.required' => 'You must agree to the Terms of Service and Privacy Policy to continue',
            'privacy_consent.accepted' => 'You must agree to the Terms of Service and Privacy Policy to continue',
        ];
    }
}
