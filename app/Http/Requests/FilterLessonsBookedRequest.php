<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\StaffBooking\ListStaffBookedLessonsAction;
use Illuminate\Foundation\Http\FormRequest;

class FilterLessonsBookedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paymentState = $this->query('payment', 'all');

        $this->merge([
            'payment' => in_array($paymentState, $this->paymentStates(), true) ? $paymentState : 'all',
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payment' => ['nullable', 'in:'.implode(',', $this->paymentStates())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment.in' => 'Choose all, unpaid, paid, expired, or cancelled.',
        ];
    }

    public function paymentState(): string
    {
        return $this->validated('payment') ?? 'all';
    }

    /**
     * @return list<string>
     */
    private function paymentStates(): array
    {
        return ['all', ...array_keys(ListStaffBookedLessonsAction::PAYMENT_STATES)];
    }
}
