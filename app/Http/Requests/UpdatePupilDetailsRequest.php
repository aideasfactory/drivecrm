<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePupilDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $student = $this->route('student');

        if (! $student instanceof Student) {
            return false;
        }

        return $this->user()?->can('updateDetails', $student) ?? false;
    }

    /**
     * Normalise the pickup point postcode to upper case with a single space
     * before the inward code (e.g. "ts71ab" becomes "TS7 1AB").
     */
    protected function prepareForValidation(): void
    {
        $pickupPoint = $this->input('pickup_point');

        if (! is_array($pickupPoint) || ! is_string($pickupPoint['postcode'] ?? null)) {
            return;
        }

        $stripped = strtoupper((string) preg_replace('/\s+/', '', $pickupPoint['postcode']));

        $pickupPoint['postcode'] = strlen($stripped) > 3
            ? substr($stripped, 0, -3).' '.substr($stripped, -3)
            : ($stripped === '' ? null : $stripped);

        $this->merge(['pickup_point' => $pickupPoint]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        /*
         * The pupil's email is synced to the linked login, where it is unique
         * and NOT NULL. Pupils with a login must keep an email, and it cannot
         * belong to another account.
         */
        $emailRules = [$student->user_id ? 'required' : 'nullable', 'string', 'email', 'max:255'];

        if ($student->user_id) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($student->user_id);
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:50'],
            'pickup_point' => ['nullable', 'array'],
            'pickup_point.label' => ['required_with:pickup_point', 'string', 'max:255'],
            'pickup_point.address' => ['required_with:pickup_point', 'string', 'max:1000'],
            'pickup_point.postcode' => ['required_with:pickup_point', 'string', 'max:10', 'regex:/^[A-Z]{1,2}[0-9][0-9A-Z]?\s?[0-9][A-Z]{2}$/i'],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'owns_account' => ['required', 'boolean'],
            'contact_first_name' => ['nullable', 'string', 'max:255'],
            'contact_surname' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'string', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'A first name is required.',
            'surname.required' => 'A surname is required.',
            'email.required' => 'This pupil has an app login, so an email address is required.',
            'email.unique' => 'This email address is already in use by another account.',
            'pickup_point.label.required_with' => 'A label for the pickup point is required.',
            'pickup_point.address.required_with' => 'The pickup address is required.',
            'pickup_point.postcode.required_with' => 'The pickup postcode is required.',
            'pickup_point.postcode.regex' => 'Please enter a valid UK postcode.',
            'date_of_birth.before_or_equal' => 'Date of birth can’t be in the future.',
            'date_of_birth.after' => 'Please enter a valid date of birth.',
        ];
    }
}
