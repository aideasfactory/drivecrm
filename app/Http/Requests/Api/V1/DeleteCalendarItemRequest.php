<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\CalendarItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteCalendarItemRequest extends FormRequest
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
            'scope' => [
                'sometimes',
                Rule::in(['single', 'future']),
            ],
            // A reason is mandatory when the slot has a booking attached
            // (cancelling a lesson), and ignored for plain availability slots.
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->itemHasBooking()),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Whether the calendar item being deleted has a lesson attached.
     */
    protected function itemHasBooking(): bool
    {
        $calendarItem = $this->route('calendarItem');

        return $calendarItem instanceof CalendarItem && $calendarItem->lessons()->exists();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to cancel this booking.',
            'reason.max' => 'The reason cannot exceed 1000 characters.',
            'scope.in' => 'Scope must be either "single" or "future".',
        ];
    }
}
