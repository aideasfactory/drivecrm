<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\LessonCancellationReason;
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
            // The structured reason for cancelling a booking. Optional so app
            // versions that only send `reason` keep working (stored as `other`).
            'reason_code' => [
                'sometimes',
                'nullable',
                Rule::enum(LessonCancellationReason::class),
            ],
            // Free-text details. Required when cancelling a booking with no
            // reason_code or with `other`; ignored for plain availability slots.
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->itemHasBooking() && $this->reasonCode()->requiresDetails()),
                'nullable',
                'string',
                'max:1000',
            ],
            // Deprecated: late cancellations are never paid out any more, so
            // this is accepted for older app versions and ignored.
            'refund_lesson' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * The chosen cancellation reason, `other` when none (or an unknown one) was sent.
     */
    public function reasonCode(): LessonCancellationReason
    {
        return LessonCancellationReason::tryFrom((string) $this->input('reason_code')) ?? LessonCancellationReason::Other;
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
            'reason.required' => 'Please add details for this cancellation.',
            'reason_code.enum' => 'Please choose a valid cancellation reason.',
            'reason.max' => 'The reason cannot exceed 1000 characters.',
            'scope.in' => 'Scope must be either "single" or "future".',
            'refund_lesson.boolean' => 'Refund lesson must be true or false.',
        ];
    }
}
