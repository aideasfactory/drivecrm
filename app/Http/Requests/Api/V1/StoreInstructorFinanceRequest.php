<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
            'category' => ['required', 'string', Rule::in($this->categoryKeys())],
            'vehicle_id' => ['nullable', 'integer', $this->vehicleOwnershipRule()],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(config('finances.payment_methods', [])))],
            'description' => ['required', 'string', 'max:255'],
            'amount_pence' => ['required', 'integer', 'min:1'],
            'is_recurring' => ['sometimes', 'boolean'],
            'recurrence_frequency' => ['nullable', 'string', 'in:weekly,monthly,yearly', 'required_if:is_recurring,true'],
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Category slugs valid for the submitted `type`.
     *
     * @return array<int, string>
     */
    private function categoryKeys(): array
    {
        $source = $this->input('type') === 'payment' ? 'payment_categories' : 'expense_categories';

        return array_keys(config("finances.{$source}", []));
    }

    /**
     * Vehicle must belong to the authenticated instructor.
     */
    private function vehicleOwnershipRule(): Exists
    {
        $instructorId = $this->user()?->instructor?->id ?? 0;

        return Rule::exists('vehicles', 'id')->where('instructor_id', $instructorId);
    }
}
