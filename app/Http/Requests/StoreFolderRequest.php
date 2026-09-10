<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:resource_folders,id'],
            'visibility' => ['required', 'string', 'in:student,instructor,both'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The folder name is required.',
            'parent_id.exists' => 'The selected parent folder does not exist.',
            'visibility.required' => 'Choose who can see this folder.',
            'visibility.in' => 'Visibility must be student, instructor, or both.',
        ];
    }
}
