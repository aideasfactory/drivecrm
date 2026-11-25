<?php

namespace App\Http\Requests\Onboarding;

use App\Models\CalendarItem;
use App\Models\Enquiry;
use App\Support\LessonLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    /**
     * Lessons are 2 hours: refuse a slot shorter than that (short slots are
     * also hidden from the step 4 calendar).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $calendarItem = CalendarItem::find($this->integer('calendar_item_id'));

                if ($calendarItem && LessonLength::slotIsTooShort($calendarItem)) {
                    $validator->errors()->add('calendar_item_id', LessonLength::TOO_SHORT_MESSAGE);
                }
            },
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
