<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        ];
    }
}
