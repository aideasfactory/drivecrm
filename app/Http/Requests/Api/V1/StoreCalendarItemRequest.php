<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\RecurrencePattern;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreCalendarItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
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
            'recurrence_pattern' => [
                'sometimes',
                new Enum(RecurrencePattern::class),
            ],
            'recurrence_end_date' => [
                'nullable',
                'date',
                'date_format:Y-m-d',
                'after:date',
            ],
            'travel_time_minutes' => [
                'nullable',
                'integer',
                'in:15,30,45',
            ],
            'is_practical_test' => [
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
     * Check if the new time slot overlaps with existing ones.
     */
    protected function checkForOverlap(Validator $validator): void
    {
        $instructor = $this->user()->instructor;
        $date = $this->input('date');
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');
        $travelMinutes = $this->integer('travel_time_minutes', 0);
        $isPracticalTest = $this->boolean('is_practical_test');

        $effectiveStartTime = $startTime;
        $effectiveEndTime = $endTime;

        if ($isPracticalTest) {
            $effectiveStartTime = \Carbon\Carbon::parse($startTime)
                ->subMinutes(60)
                ->format('H:i');
            $effectiveEndTime = \Carbon\Carbon::parse($endTime)
                ->addMinutes(30)
                ->format('H:i');
        } elseif ($travelMinutes > 0) {
            $effectiveEndTime = \Carbon\Carbon::parse($endTime)
                ->addMinutes($travelMinutes)
                ->format('H:i');
        }

        $calendar = $instructor->calendars()
            ->where('date', $date)
            ->first();

        if (! $calendar) {
            return;
        }

        $overlap = $calendar->items()
            ->where(function ($query) use ($effectiveStartTime, $effectiveEndTime): void {
                $query->whereRaw('TIME(?) < TIME(end_time)', [$effectiveStartTime])
                    ->whereRaw('TIME(?) > TIME(start_time)', [$effectiveEndTime]);
            })
            ->exists();

        if ($overlap) {
            $validator->errors()->add(
                'start_time',
                'This time slot (including travel time) overlaps with an existing time slot.'
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
            'date.after_or_equal' => 'Cannot create time slots in the past.',
            'start_time.required' => 'Please provide a start time.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'start_time.after_or_equal' => 'Start time must be at or after '.config('diary.start_time').'.',
            'end_time.required' => 'Please provide an end time.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'end_time.before_or_equal' => 'End time must be at or before '.config('diary.end_time').'.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'unavailability_reason.max' => 'Unavailability reason cannot exceed 500 characters.',
            'recurrence_pattern' => 'Please select a valid recurrence pattern.',
            'recurrence_end_date.date' => 'Please provide a valid end date for the recurrence.',
            'recurrence_end_date.after' => 'Recurrence end date must be after the start date.',
            'travel_time_minutes.integer' => 'Travel time must be a number.',
            'travel_time_minutes.in' => 'Travel time must be 15, 30, or 45 minutes.',
        ];
    }
}
