<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstructorFinanceRequest extends FormRequest
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
            'type' => ['required', 'string', 'in:payment,expense'],
            'description' => ['required', 'string', 'max:255'],
            'amount_pence' => ['required', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', 'in:weekly,monthly,yearly', 'required_if:is_recurring,true'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
