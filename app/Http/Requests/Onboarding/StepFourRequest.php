<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Enquiry;
use Illuminate\Foundation\Http\FormRequest;

class StepFourRequest extends FormRequest
{
    /**
     * Furthest ahead a learner may book their first lesson. Staff bookings
     * run by an owner have no forward limit.
     */
    public const LEARNER_MAX_DAYS_AHEAD = 30;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $enquiry = $this->get('enquiry');
        $hasNoForwardLimit = $enquiry instanceof Enquiry && $enquiry->isStaffBookingBy($this->user());

        $dateRules = [
            'required',
            'date',
            'after_or_equal:'.now()->addDays(2)->format('Y-m-d'), // Must be at least 2 days from now
        ];

        if (! $hasNoForwardLimit) {
            $dateRules[] = 'before_or_equal:'.now()->addDays(self::LEARNER_MAX_DAYS_AHEAD)->format('Y-m-d');
        }

        return [
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'date' => $dateRules,
            'calendar_item_id' => ['required', 'exists:calendar_items,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Lessons must be booked at least 2 days in advance.',
            'date.before_or_equal' => 'Lessons cannot be booked more than '.self::LEARNER_MAX_DAYS_AHEAD.' days in advance.',
        ];
    }
}
