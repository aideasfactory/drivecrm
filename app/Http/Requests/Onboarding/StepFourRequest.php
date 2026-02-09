<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class StepFourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'date' => [
                'required',
                'date',
                'after_or_equal:'.now()->addDays(2)->format('Y-m-d'), // Must be at least 2 days from now
                'before_or_equal:'.now()->addDays(30)->format('Y-m-d'), // Maximum 30 days ahead
            ],
            'calendar_item_id' => ['required', 'exists:calendar_items,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Lessons must be booked at least 2 days in advance.',
            'date.before_or_equal' => 'Lessons cannot be booked more than 30 days in advance.',
        ];
    }
}
