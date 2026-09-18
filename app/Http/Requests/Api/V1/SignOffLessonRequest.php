<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\ReflectiveLog;
use Illuminate\Foundation\Http\FormRequest;

class SignOffLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = ReflectiveLog::normalizePayload($this->all());

        if ($normalized !== null) {
            $this->merge([
                'reflective_log' => $normalized,
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
            'reflective_log' => ['nullable', 'array'],
            'reflective_log.what_i_learned' => ['required_with:reflective_log', 'string', 'max:5000'],
            'reflective_log.what_went_well' => ['required_with:reflective_log', 'string', 'max:5000'],
            'reflective_log.what_to_improve' => ['required_with:reflective_log', 'string', 'max:5000'],
            'reflective_log.additional_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'summary.required' => 'The summary field is required.',
            'reflective_log.what_i_learned.required_with' => 'Please complete what was learned before signing off.',
            'reflective_log.what_went_well.required_with' => 'Please complete what went well before signing off.',
            'reflective_log.what_to_improve.required_with' => 'Please complete what to improve before signing off.',
        ];
    }
}
