<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'default_slot_duration_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'The primary colour must be a valid hex colour (e.g. #FF5733).',
            'default_slot_duration_minutes.min' => 'The default slot duration must be at least 30 minutes.',
            'default_slot_duration_minutes.max' => 'The default slot duration cannot exceed 480 minutes (8 hours).',
        ];
    }
}
