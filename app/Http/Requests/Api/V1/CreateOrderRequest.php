<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMode;
use App\Models\CalendarItem;
use App\Support\LessonLength;
use App\Support\UnscheduledLessons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateOrderRequest extends FormRequest
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
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'payment_mode' => ['required', 'string', Rule::in([PaymentMode::UPFRONT->value, PaymentMode::WEEKLY->value])],
            'calendar_item_id' => ['nullable', 'integer', 'exists:calendar_items,id'],
            'first_lesson_date' => ['required_without:calendar_item_id', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required_without:calendar_item_id', 'nullable', 'string', 'date_format:H:i'],
            'end_time' => ['required_without:calendar_item_id', 'nullable', 'string', 'date_format:H:i', 'after:start_time'],
            UnscheduledLessons::CONFIRM_FIELD => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Lessons are 2 hours: refuse a slot (or typed times) shorter than that.
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

                if ($this->filled('calendar_item_id')) {
                    $calendarItem = CalendarItem::find($this->integer('calendar_item_id'));

                    if ($calendarItem && LessonLength::slotIsTooShort($calendarItem)) {
                        $validator->errors()->add('calendar_item_id', LessonLength::TOO_SHORT_MESSAGE);
                    }

                    return;
                }

                if (LessonLength::isTooShort($this->input('start_time'), $this->input('end_time'))) {
                    $validator->errors()->add('end_time', LessonLength::TOO_SHORT_MESSAGE);
                }
            },
            function (Validator $validator): void {
                UnscheduledLessons::checkBooking(
                    $validator,
                    $this->user(),
                    $this->route('student'),
                    $this->boolean(UnscheduledLessons::CONFIRM_FIELD),
                );
            },
        ];
    }
}
