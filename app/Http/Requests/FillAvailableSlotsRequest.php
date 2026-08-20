<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class FillAvailableSlotsRequest extends FormRequest
{
    /**
     * Lesson slot length in minutes — a day window must fit at least one slot.
     */
    private const SLOT_DURATION_MINUTES = 120;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'weeks' => [
                'required',
                'integer',
                'min:1',
                'max:12',
            ],
            'days' => [
                'required',
                'array',
                'min:1',
            ],
            'days.*' => [
                'integer',
                'between:1,7',
            ],
            'day_start_time' => [
                'required',
                'date_format:H:i',
                'after_or_equal:'.config('diary.start_time'),
            ],
            'day_end_time' => [
                'required',
                'date_format:H:i',
                'after:day_start_time',
                'before_or_equal:'.config('diary.end_time'),
            ],
            'travel_time_minutes' => [
                'nullable',
                'integer',
                'in:15,30,45',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            [$startHours, $startMinutes] = array_map('intval', explode(':', $this->input('day_start_time')));
            [$endHours, $endMinutes] = array_map('intval', explode(':', $this->input('day_end_time')));

            $windowMinutes = ($endHours * 60 + $endMinutes) - ($startHours * 60 + $startMinutes);

            if ($windowMinutes < self::SLOT_DURATION_MINUTES) {
                $validator->errors()->add(
                    'day_end_time',
                    'The daily window must be at least 2 hours to fit a lesson slot.'
                );
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'The start date cannot be in the past.',
            'weeks.required' => 'Please choose how many weeks to fill.',
            'weeks.min' => 'Please choose at least 1 week.',
            'weeks.max' => 'You can fill at most 12 weeks at a time.',
            'days.required' => 'Please select at least one day of the week.',
            'days.min' => 'Please select at least one day of the week.',
            'days.*.between' => 'Invalid day of the week selected.',
            'day_start_time.required' => 'Please provide a daily start time.',
            'day_start_time.date_format' => 'Start time must be in HH:MM format.',
            'day_end_time.required' => 'Please provide a daily end time.',
            'day_end_time.date_format' => 'End time must be in HH:MM format.',
            'day_end_time.after' => 'The daily end time must be after the start time.',
            'travel_time_minutes.in' => 'Travel time must be 15, 30, or 45 minutes.',
        ];
    }
}
