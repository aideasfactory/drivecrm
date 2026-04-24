<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMileageLogRequest extends FormRequest
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
            'date' => ['required', 'date', 'date_format:Y-m-d'],
            'start_mileage' => ['required', 'integer', 'min:0'],
            'end_mileage' => ['required', 'integer', 'min:0', 'gte:start_mileage'],
            'type' => ['required', 'string', Rule::in(array_keys(config('finances.mileage_types', [])))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
