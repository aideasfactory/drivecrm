<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class SubmitHazardPerceptionAttemptRequest extends FormRequest
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
            'taps' => ['required', 'array'],
            'taps.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'taps.required' => 'An array of tap timestamps is required.',
            'taps.array' => 'Taps must be an array of timestamps (seconds into the video).',
            'taps.*.numeric' => 'Each tap must be a number representing seconds into the video.',
            'taps.*.min' => 'Tap timestamps cannot be negative.',
        ];
    }
}
