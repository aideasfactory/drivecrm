<?php

declare(strict_types=1);

namespace App\Http\Requests\Hmrc\Vehicles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewInsuranceSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->instructor !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.finance_row_id' => ['required', 'integer', 'min:1'],
            'decisions.*.target_category' => ['required', Rule::in(['vehicle_insurance', 'business_insurance'])],
        ];
    }
}
