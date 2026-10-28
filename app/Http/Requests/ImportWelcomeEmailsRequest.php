<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportWelcomeEmailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Route is owner-only (EnsureOwner middleware).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `send` false (default) only previews who would be emailed.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:1024'],
            'send' => ['sometimes', 'boolean'],
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
            'file.required' => 'Please choose a CSV of instructor emails.',
            'file.extensions' => 'The file must be a .csv (or .txt) of instructor emails.',
            'file.max' => 'The CSV must not be larger than 1MB.',
        ];
    }
}
