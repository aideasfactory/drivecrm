<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Support\ContactDetails;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StepFiveRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const LEARNER_FIELDS = [
        'learner_first_name',
        'learner_last_name',
        'learner_phone',
        'learner_email',
        'learner_dob',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Drop learner details entirely when not booking for someone else, so
     * stale values from a previously ticked box can never block the booker.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->boolean('booking_for_someone_else')) {
            $this->merge(array_fill_keys(self::LEARNER_FIELDS, null));

            return;
        }

        if (is_string($this->input('learner_phone'))) {
            $this->merge(['learner_phone' => ContactDetails::normaliseUkMobile($this->input('learner_phone'))]);
        }
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
            'learner_first_name' => ['nullable', 'string', 'max:100'],
            'learner_last_name' => ['nullable', 'string', 'max:100'],
            'learner_email' => ['nullable', 'string', 'max:255'],
            'learner_phone' => ['nullable', 'string', 'max:20'],
            'learner_dob' => ['nullable', 'string', 'max:20'],
        ];

        if ($this->boolean('booking_for_someone_else') && ! $this->boolean('auto_save')) {
            $minimumAge = ContactDetails::learnerMinimumAge();
            $latestAllowedDob = now()->startOfDay()->subYears($minimumAge);

            $rules['learner_first_name'] = ['required', 'string', 'max:100', 'regex:'.ContactDetails::NAME_REGEX];
            $rules['learner_last_name'] = ['required', 'string', 'max:100', 'regex:'.ContactDetails::NAME_REGEX];
            $rules['learner_phone'] = ['required', 'string', 'regex:'.ContactDetails::UK_MOBILE_REGEX];
            $rules['learner_email'] = ['required', 'email', 'max:255'];
            $rules['learner_dob'] = [
                'bail',
                'required',
                'date',
                'before_or_equal:today',
                function (string $attribute, mixed $value, Closure $fail) use ($latestAllowedDob, $minimumAge): void {
                    if (Carbon::parse($value)->startOfDay()->gt($latestAllowedDob)) {
                        $fail("The learner must be at least {$minimumAge} years old");
                    }
                },
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'learner_first_name.required' => 'Please enter the learner’s first name',
            'learner_first_name.regex' => 'First name can only contain letters, spaces, hyphens and apostrophes',
            'learner_first_name.max' => 'First name must be 100 characters or fewer',
            'learner_last_name.required' => 'Please enter the learner’s last name',
            'learner_last_name.regex' => 'Last name can only contain letters, spaces, hyphens and apostrophes',
            'learner_last_name.max' => 'Last name must be 100 characters or fewer',
            'learner_phone.required' => 'Please enter the learner’s mobile number',
            'learner_phone.regex' => 'Please enter a valid UK mobile number, e.g. 07700 900123 or +44 7700 900123',
            'learner_email.required' => 'Please enter the learner’s email address',
            'learner_email.email' => 'Please enter a valid email address',
            'learner_email.max' => 'Email address must be 255 characters or fewer',
            'learner_dob.required' => 'Please enter the learner’s date of birth',
            'learner_dob.date' => 'Please enter a valid date of birth',
            'learner_dob.before_or_equal' => 'Date of birth can’t be in the future',
        ];
    }
}
