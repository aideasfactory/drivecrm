<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\LessonsBooked\BuildBookedLessonRowsAction;
use Illuminate\Foundation\Http\FormRequest;

class FilterLessonsBookedRequest extends FormRequest
{
    /**
     * 'staff' = bookings team only, 'all' = every booking.
     *
     * @var list<string>
     */
    private const TABS = ['staff', 'all'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $paymentState = $this->query('payment', 'all');
        $tab = $this->query('tab', 'staff');

        $this->merge([
            'payment' => in_array($paymentState, $this->paymentStates(), true) ? $paymentState : 'all',
            'tab' => in_array($tab, self::TABS, true) ? $tab : 'staff',
            'from' => $this->query('from') ?: null,
            'to' => $this->query('to') ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'payment' => ['nullable', 'in:'.implode(',', $this->paymentStates())],
            'tab' => ['nullable', 'in:'.implode(',', self::TABS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment.in' => 'Choose all, unpaid, paid, expired, or cancelled.',
            'to.after_or_equal' => 'The "to" date must be on or after the "from" date.',
        ];
    }

    public function paymentState(): string
    {
        return $this->validated('payment') ?? 'all';
    }

    /**
     * @return array{tab: string, payment: string, from: ?string, to: ?string}
     */
    public function filters(): array
    {
        return [
            'tab' => $this->validated('tab') ?? 'staff',
            'payment' => $this->paymentState(),
            'from' => $this->validated('from'),
            'to' => $this->validated('to'),
        ];
    }

    /**
     * @return list<string>
     */
    private function paymentStates(): array
    {
        return ['all', ...array_keys(BuildBookedLessonRowsAction::PAYMENT_STATES)];
    }
}
