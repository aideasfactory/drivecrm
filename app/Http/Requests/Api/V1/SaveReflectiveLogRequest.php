<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\ReflectiveLog;
use Illuminate\Foundation\Http\FormRequest;

class SaveReflectiveLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = ReflectiveLog::normalizePayload($this->all());

        if ($normalized !== null) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'what_i_learned' => ['required', 'string', 'max:5000'],
            'what_went_well' => ['required', 'string', 'max:5000'],
            'what_to_improve' => ['required', 'string', 'max:5000'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'what_i_learned.required' => 'Please complete what was learned before saving the reflective log.',
            'what_went_well.required' => 'Please complete what went well before saving the reflective log.',
            'what_to_improve.required' => 'Please complete what to improve before saving the reflective log.',
        ];
    }
}
