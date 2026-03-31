<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

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
            'description' => ['sometimes', 'string', 'max:255'],
            'amount_pence' => ['sometimes', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', 'in:weekly,monthly,yearly'],
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
