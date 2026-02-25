<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreResourceRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:512000', 'mimes:mp4,webm,mov,avi,mkv,pdf'],
            'title' => ['required', 'string', 'max:255'],
            'resource_folder_id' => ['required', 'integer', 'exists:resource_folders,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'The file must not be larger than 500MB.',
            'file.mimes' => 'The file must be a video (mp4, webm, mov, avi, mkv) or PDF.',
            'title.required' => 'The resource title is required.',
            'resource_folder_id.required' => 'Please select a folder.',
            'resource_folder_id.exists' => 'The selected folder does not exist.',
        ];
    }
}
