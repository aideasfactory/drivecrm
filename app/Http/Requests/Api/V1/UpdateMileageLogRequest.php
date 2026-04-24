<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\MileageLog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMileageLogRequest extends FormRequest
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
            'date' => ['sometimes', 'date', 'date_format:Y-m-d'],
            'start_mileage' => ['sometimes', 'integer', 'min:0'],
            'end_mileage' => ['sometimes', 'integer', 'min:0'],
            'type' => ['sometimes', 'string', Rule::in(array_keys(config('finances.mileage_types', [])))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Enforce `end_mileage >= effective start_mileage` even when only one of the two fields is sent.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $log = $this->route('mileageLog');
            $existingStart = $log instanceof MileageLog ? $log->start_mileage : 0;
            $existingEnd = $log instanceof MileageLog ? $log->end_mileage : 0;

            $start = $this->has('start_mileage') ? (int) $this->input('start_mileage') : $existingStart;
            $end = $this->has('end_mileage') ? (int) $this->input('end_mileage') : $existingEnd;

            if ($end < $start) {
                $v->errors()->add('end_mileage', 'The end mileage must be greater than or equal to the start mileage.');
            }
        });
    }
}
