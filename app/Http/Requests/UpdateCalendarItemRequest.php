<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCalendarItemRequest extends FormRequest
{
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'is_available' => [
                'sometimes',
                'boolean',
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

            $this->checkForOverlap($validator);
        });
    }

    /**
     * Check if the updated time slot overlaps with existing ones (excluding itself).
     */
    protected function checkForOverlap(Validator $validator): void
    {
        $instructor = $this->route('instructor');
        $calendarItem = $this->route('calendarItem');
        $date = $this->input('date');
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');

        $calendar = $instructor->calendars()
            ->where('date', $date)
            ->first();

        if (! $calendar) {
            return;
        }

        $overlap = $calendar->items()
            ->where('id', '!=', $calendarItem->id)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereRaw('TIME(?) < TIME(end_time)', [$startTime])
                    ->whereRaw('TIME(?) > TIME(start_time)', [$endTime]);
            })
            ->exists();

        if ($overlap) {
            $validator->errors()->add(
                'start_time',
                'This time slot overlaps with an existing time slot.'
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Please select a date for the time slot.',
            'date.date' => 'Please provide a valid date.',
            'date.date_format' => 'Date must be in YYYY-MM-DD format.',
            'date.after_or_equal' => 'Cannot move time slots to the past.',
            'start_time.required' => 'Please provide a start time.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'Please provide an end time.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
        ];
    }
}
