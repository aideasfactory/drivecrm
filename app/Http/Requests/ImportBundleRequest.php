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
     * The zip is checked by its filename extension, not `mimes:zip`. PHP's
     * fileinfo often sniffs a zip of CSVs as application/octet-stream or
     * text/plain, which fails `mimes:zip` before the bundle is read. ZipArchive
     * still rejects anything that is not a real zip.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:zip', 'max:102400'],
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
            'file.extensions' => 'The file must be a .zip of the import CSVs.',
            'file.max' => 'The zip must not be larger than 100MB.',
        ];
    }
}
