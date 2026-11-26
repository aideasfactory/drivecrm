<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Calendar\DescribeBlockingCalendarItemAction;
use App\Actions\Instructor\UpdateTravelTimeAction;
use App\Models\CalendarItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTravelTimeRequest extends FormRequest
{
    /**
     * Owner-only: enforced by the EnsureOwner route middleware.
     */
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
            'travel_time_minutes' => ['required', 'integer', 'in:0,15,30,45,60'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->checkItemType($validator);

            if ($validator->errors()->isEmpty() && $this->integer('travel_time_minutes') > 0) {
                $this->checkTravelWindow($validator);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'travel_time_minutes.required' => 'Please choose a travel time.',
            'travel_time_minutes.in' => 'Travel time must be 0, 15, 30, 45 or 60 minutes.',
        ];
    }

    protected function checkItemType(Validator $validator): void
    {
        /** @var CalendarItem $calendarItem */
        $calendarItem = $this->route('calendarItem');

        if ($calendarItem->isPracticalTest()) {
            $validator->errors()->add('travel_time_minutes', 'Practical tests do not have travel time.');
        }
    }

    /**
     * The new travel time must fit before midnight and not run into anything
     * else in the diary (other than the slot's own travel block).
     */
    protected function checkTravelWindow(Validator $validator): void
    {
        /** @var CalendarItem $calendarItem */
        $calendarItem = $this->route('calendarItem');
        $slot = $calendarItem->isTravel() ? ($calendarItem->parentItem ?? $calendarItem) : $calendarItem;
        $window = UpdateTravelTimeAction::travelWindow($slot, $this->integer('travel_time_minutes'));

        if ($window['end_time'] <= $window['start_time']) {
            $validator->errors()->add('travel_time_minutes', 'This travel time would run past midnight.');

            return;
        }

        $clash = CalendarItem::query()
            ->where('calendar_id', $slot->calendar_id)
            ->whereKeyNot(array_filter([$slot->id, $calendarItem->id, $slot->travelItem?->id]))
            ->overlapping($window['start_time'], $window['end_time'])
            ->with(['student', 'lessons.order.student', 'parentItem.lessons.order.student'])
            ->orderBy('start_time')
            ->first();

        if ($clash !== null) {
            $validator->errors()->add(
                'travel_time_minutes',
                'This travel time would overlap '.app(DescribeBlockingCalendarItemAction::class)($clash).'. Choose a shorter travel time or move that first.',
            );
        }
    }
}
