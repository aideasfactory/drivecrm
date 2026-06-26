<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCalendarItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
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
                'after_or_equal:'.config('diary.start_time'),
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
                'before_or_equal:'.config('diary.end_time'),
            ],
            'is_available' => [
                'sometimes',
                'boolean',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'unavailability_reason' => [
                'nullable',
                'string',
                'max:500',
            ],
            'travel_time_minutes' => [
                'nullable',
                'integer',
                'in:0,15,30,45',
            ],
            'apply_to_future_in_order' => [
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
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkForOverlap($validator);
        });
    }

    /**
     * Check if the updated time slot overlaps with existing ones (excluding itself).
     *
     * Scoped to the authenticated instructor (token-derived), never a client-sent ID.
     */
    protected function checkForOverlap(Validator $validator): void
    {
        $instructor = $this->user()->instructor;
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
            ->where(function ($query) use ($startTime, $endTime): void {
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
            'start_time.after_or_equal' => 'Start time must be at or after '.config('diary.start_time').'.',
            'end_time.required' => 'Please provide an end time.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'end_time.before_or_equal' => 'End time must be at or before '.config('diary.end_time').'.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'unavailability_reason.max' => 'Unavailability reason cannot exceed 500 characters.',
            'travel_time_minutes.in' => 'Travel time must be 0, 15, 30, or 45 minutes.',
        ];
    }
}
