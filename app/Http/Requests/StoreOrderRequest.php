<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use App\Models\Package;
use App\Models\Student;
use App\Support\CashBookings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
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
            'payment_mode' => ['required', 'string', Rule::in([PaymentMode::UPFRONT->value, PaymentMode::WEEKLY->value, PaymentMode::CASH->value])],
            'cash_note' => ['nullable', 'string', 'max:500'],
            'calendar_item_id' => ['nullable', 'integer', 'exists:calendar_items,id'],
            'first_lesson_date' => ['required_without:calendar_item_id', 'nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['required_without:calendar_item_id', 'nullable', 'string', 'date_format:H:i'],
            'end_time' => ['required_without:calendar_item_id', 'nullable', 'string', 'date_format:H:i', 'after:start_time'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('payment_mode') !== PaymentMode::WEEKLY->value) {
                    return;
                }

                $package = Package::find($this->integer('package_id'));

                if ($package && ! $package->allowsWeeklyPayment()) {
                    $validator->errors()->add('payment_mode', 'Single lesson bookings must be paid in full.');
                }
            },
            function (Validator $validator): void {
                if ($this->input('payment_mode') !== PaymentMode::CASH->value) {
                    return;
                }

                $student = $this->route('student');

                if (! $student instanceof Student || ! CashBookings::allowedFor($this->user(), $student)) {
                    $validator->errors()->add('payment_mode', 'Cash bookings are only for pupils with cash payments turned on, booked by an owner with full access.');
                }
            },
        ];
    }
}
