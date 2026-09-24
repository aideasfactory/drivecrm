<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportBundleRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:zip', 'max:102400'],
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
            'file.required' => 'Please choose a zip file to upload.',
            'file.mimes' => 'The file must be a .zip of the import CSVs.',
            'file.max' => 'The zip must not be larger than 100MB.',
        ];
    }
}
