<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LowAvailabilitySettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true && ! $this->user()->isRestrictedOwner();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'weeks' => ['required', 'integer', 'min:1', 'max:12'],
            'min_slots_per_week' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weeks.min' => 'Check at least 1 week ahead.',
            'weeks.max' => 'You can check up to 12 weeks ahead.',
            'min_slots_per_week.min' => 'The minimum must be at least 1 slot per week.',
            'min_slots_per_week.max' => 'The minimum can be at most 100 slots per week.',
        ];
    }
}
