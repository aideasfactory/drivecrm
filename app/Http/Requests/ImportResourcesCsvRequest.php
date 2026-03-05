<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ImportResourcesCsvRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'resource_folder_id' => ['required', 'integer', 'exists:resource_folders,id'],
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
            'file.required' => 'Please select a CSV file to upload.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The CSV file must not be larger than 5MB.',
            'resource_folder_id.required' => 'Please select a folder to import resources into.',
            'resource_folder_id.exists' => 'The selected folder does not exist.',
        ];
    }
}
