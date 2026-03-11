<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCalendarItemRequest extends FormRequest
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
        ];
    }

    /**
     * Travel time buffer in minutes between consecutive availability blocks.
     */
    protected const TRAVEL_TIME_MINUTES = 30;

    /**
     * Required block duration in hours.
     */
    protected const BLOCK_DURATION_HOURS = 2;

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkBlockDuration($validator);
            $this->checkForOverlapWithTravelTime($validator);
            $this->checkUnavailabilityReason($validator);
        });
    }

    /**
     * Ensure the block is exactly 2 hours long.
     */
    protected function checkBlockDuration(Validator $validator): void
    {
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');

        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);
        $expectedDuration = self::BLOCK_DURATION_HOURS * 60;

        if (($endMinutes - $startMinutes) !== $expectedDuration) {
            $validator->errors()->add(
                'end_time',
                'Availability blocks must be exactly '.self::BLOCK_DURATION_HOURS.' hours long.'
            );
        }
    }

    /**
     * Check unavailability reason is provided when marking as unavailable.
     */
    protected function checkUnavailabilityReason(Validator $validator): void
    {
        $isAvailable = $this->boolean('is_available', true);
        $unavailabilityReason = $this->input('unavailability_reason');

        if (! $isAvailable && empty($unavailabilityReason)) {
            $validator->errors()->add(
                'unavailability_reason',
                'Please provide a reason when marking this slot as unavailable.'
            );
        }
    }

    /**
     * Check if the new time slot overlaps with or violates travel time against existing slots.
     *
     * Travel time (30 min) sits outside the 2-hour block. Between consecutive
     * blocks on the same day there must be at least a 30-minute gap.
     */
    protected function checkForOverlapWithTravelTime(Validator $validator): void
    {
        $instructor = $this->route('instructor');
        $date = $this->input('date');
        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');

        $calendar = $instructor->calendars()
            ->where('date', $date)
            ->first();

        if (! $calendar) {
            return;
        }

        $travelMinutes = self::TRAVEL_TIME_MINUTES;

        // Expand the new slot's window by travel time on both sides to catch conflicts.
        // A conflict exists when an existing slot's end_time + travel overlaps with new start,
        // or new end_time + travel overlaps with existing start.
        $bufferedStart = $this->subtractMinutes($startTime, $travelMinutes);
        $bufferedEnd = $this->addMinutes($endTime, $travelMinutes);

        $conflict = $calendar->items()
            ->where(function ($query) use ($bufferedStart, $bufferedEnd) {
                $query->whereRaw('TIME(?) < TIME(end_time)', [$bufferedStart])
                    ->whereRaw('TIME(?) > TIME(start_time)', [$bufferedEnd]);
            })
            ->exists();

        if ($conflict) {
            $validator->errors()->add(
                'start_time',
                'This time slot conflicts with an existing slot. There must be at least '.self::TRAVEL_TIME_MINUTES.' minutes of travel time between blocks.'
            );
        }
    }

    /**
     * Convert HH:MM time string to total minutes.
     */
    protected function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    /**
     * Add minutes to a HH:MM time string.
     */
    protected function addMinutes(string $time, int $minutes): string
    {
        $total = $this->timeToMinutes($time) + $minutes;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    /**
     * Subtract minutes from a HH:MM time string.
     */
    protected function subtractMinutes(string $time, int $minutes): string
    {
        $total = max(0, $this->timeToMinutes($time) - $minutes);

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
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
            'date.after_or_equal' => 'Cannot create time slots in the past.',
            'start_time.required' => 'Please provide a start time.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'Please provide an end time.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'end_time.after' => 'End time must be after start time.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
            'unavailability_reason.max' => 'Unavailability reason cannot exceed 500 characters.',
        ];
    }
}
