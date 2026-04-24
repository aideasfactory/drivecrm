<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\InstructorFinance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstructorFinanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:payment,expense'],
            'category' => ['sometimes', 'string', Rule::in($this->categoryKeys())],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(config('finances.payment_methods', [])))],
            'description' => ['sometimes', 'string', 'max:255'],
            'amount_pence' => ['sometimes', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', 'in:weekly,monthly,yearly'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Category keys valid for the effective `type` (request value, falling back to the record's current type).
     *
     * @return array<int, string>
     */
    private function categoryKeys(): array
    {
        $finance = $this->route('finance');
        $existingType = $finance instanceof InstructorFinance ? $finance->type : null;
        $type = $this->input('type', $existingType);
        $source = $type === 'payment' ? 'payment_categories' : 'expense_categories';

        return array_keys(config("finances.{$source}", []));
    }
}
