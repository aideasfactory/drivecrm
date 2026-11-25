<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CalendarItem;
use App\Support\LessonLength;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSlotOfferRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'max:1000'],
            'package_id' => ['required_without:one_off_price_pence', 'prohibits:one_off_price_pence', 'nullable', 'integer', 'exists:packages,id'],
            'one_off_price_pence' => ['required_without:package_id', 'prohibits:package_id', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * An offered slot becomes a lesson booking, so it must be 2 hours long.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $calendarItem = $this->route('calendarItem');

                if ($calendarItem instanceof CalendarItem && LessonLength::slotIsTooShort($calendarItem)) {
                    $validator->errors()->add('calendar_item', 'Lessons are 2 hours. Only a 2-hour slot can be offered.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'package_id.required_without' => 'Select a package or enter a one-off price.',
            'one_off_price_pence.required_without' => 'Select a package or enter a one-off price.',
            'one_off_price_pence.min' => 'The one-off price must be at least 1 pence.',
            'message.max' => 'The message cannot exceed 1000 characters.',
        ];
    }
}
