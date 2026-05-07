<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ItsaExpenseCategory;
use App\Support\HmrcMoney;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SubmitQuarterlyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->instructor !== null;
    }

    /**
     * Convert pound-string inputs into pence integers BEFORE validation so the
     * DB-shaped fields are what the action sees.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->has('turnover')) {
            $merge['turnover_pence'] = $this->safePence($this->input('turnover'));
        }
        if ($this->has('other_income')) {
            $merge['other_income_pence'] = $this->safePence($this->input('other_income'));
        }
        if ($this->filled('consolidated_expenses')) {
            $merge['consolidated_expenses_pence'] = $this->safePence($this->input('consolidated_expenses'));
        } elseif ($this->has('consolidated_expenses')) {
            $merge['consolidated_expenses_pence'] = null;
        }

        $expenses = is_array($this->input('expenses')) ? $this->input('expenses') : [];
        $convertedExpenses = [];
        foreach (ItsaExpenseCategory::cases() as $category) {
            $value = $expenses[$category->value] ?? null;
            $convertedExpenses[$category->value] = ($value === null || $value === '')
                ? null
                : $this->safePence($value);
        }
        $merge['expenses'] = $convertedExpenses;

        $merge['attestation'] = $this->boolean('attestation');

        $this->merge($merge);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'period_start_date' => ['required', 'date_format:Y-m-d'],
            'period_end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start_date'],

            'turnover_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'other_income_pence' => ['required', 'integer', 'min:0', 'max:9999999999'],

            'consolidated_expenses_pence' => ['nullable', 'integer', 'min:0', 'max:9999999999'],
            'expenses' => ['nullable', 'array'],
            'expenses.*' => ['nullable', 'integer', 'min:0', 'max:9999999999'],

            'attestation' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $consolidated = $this->input('consolidated_expenses_pence');
            $expenses = is_array($this->input('expenses')) ? $this->input('expenses') : [];
            $hasItemised = false;
            foreach ($expenses as $value) {
                if ($value !== null) {
                    $hasItemised = true;
                    break;
                }
            }

            if ($consolidated !== null && $hasItemised) {
                $validator->errors()->add(
                    'consolidated_expenses',
                    'Choose either consolidated expenses or itemised categories — not both.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_end_date.after_or_equal' => 'Period end date must be on or after the period start date.',
            'attestation.accepted' => 'You must confirm the digital-records attestation before submitting.',
        ];
    }

    private function safePence(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return HmrcMoney::fromInput(is_string($value) ? $value : (float) $value);
        } catch (InvalidArgumentException) {
            // Returning a sentinel makes the standard integer/min/max rules
            // generate the user-facing validation error instead of a 500.
            return -1;
        }
    }
}
