<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Support\PersonDetails;
use Illuminate\Foundation\Http\FormRequest;

class StepFiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim learner names and normalise the learner mobile into E.164 so
     * "07700 900123" and "+44 7700 900123" are stored identically.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['learner_first_name', 'learner_last_name', 'learner_email'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (is_string($this->input('learner_phone'))) {
            $merge['learner_phone'] = PersonDetails::normaliseUkMobile($this->input('learner_phone'));
        }

        $this->merge($merge);
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
            'auto_save' => ['nullable', 'boolean'],
        ];

        $learnerFields = ['learner_first_name', 'learner_last_name', 'learner_email', 'learner_phone', 'learner_dob'];

        if (! $this->boolean('booking_for_someone_else')) {
            foreach ($learnerFields as $field) {
                $rules[$field] = ['exclude'];
            }

            return $rules;
        }

        if ($this->boolean('auto_save')) {
            foreach ($learnerFields as $field) {
                $rules[$field] = ['nullable', 'string', 'max:255'];
            }

            return $rules;
        }

        return [
            ...$rules,
            'learner_first_name' => PersonDetails::nameRules(),
            'learner_last_name' => PersonDetails::nameRules(),
            'learner_email' => PersonDetails::emailRules(),
            'learner_phone' => PersonDetails::ukMobileRules(),
            'learner_dob' => PersonDetails::dateOfBirthRules(),
        ];
    }

    public function messages(): array
    {
        $minimumAge = PersonDetails::minimumAge();

        return [
            'learner_first_name.required' => "Enter the learner's first name",
            'learner_first_name.regex' => 'First name can only contain letters, spaces, hyphens and apostrophes',
            'learner_first_name.max' => 'First name must be 100 characters or fewer',
            'learner_last_name.required' => "Enter the learner's last name",
            'learner_last_name.regex' => 'Last name can only contain letters, spaces, hyphens and apostrophes',
            'learner_last_name.max' => 'Last name must be 100 characters or fewer',
            'learner_email.required' => "Enter the learner's email address",
            'learner_email.email' => 'Enter a valid email address, e.g. name@example.com',
            'learner_email.regex' => 'Enter a valid email address, e.g. name@example.com',
            'learner_email.max' => 'Email address must be 255 characters or fewer',
            'learner_phone.required' => "Enter the learner's mobile number",
            'learner_phone.regex' => 'Enter a valid UK mobile number starting 07 or +447, e.g. 07700 900123',
            'learner_dob.required' => "Enter the learner's date of birth",
            'learner_dob.date_format' => 'Enter a valid date of birth',
            'learner_dob.before' => 'Date of birth cannot be in the future',
            'learner_dob.before_or_equal' => "The learner must be at least {$minimumAge} years old to book lessons",
        ];
    }
}
