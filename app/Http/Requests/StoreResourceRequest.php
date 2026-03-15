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
        $rules = [
            'resource_type' => ['required', 'string', 'in:file,video_link'],
            'title' => ['required', 'string', 'max:255'],
            'resource_folder_id' => ['required', 'integer', 'exists:resource_folders,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
        ];

        if ($this->input('resource_type') === 'video_link') {
            $rules['video_url'] = ['required', 'url', 'max:500', 'regex:/^https?:\/\/(www\.)?(youtube\.com|youtu\.be|vimeo\.com)\/.+/i'];
            $rules['thumbnail_url'] = ['nullable', 'url', 'max:500'];
        } else {
            $rules['file'] = ['required', 'file', 'max:512000', 'mimes:mp4,webm,mov,avi,mkv,pdf'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resource_type.required' => 'Please select a resource type.',
            'resource_type.in' => 'The resource type must be file or video link.',
            'file.required' => 'Please select a file to upload.',
            'file.max' => 'The file must not be larger than 500MB.',
            'file.mimes' => 'The file must be a video (mp4, webm, mov, avi, mkv) or PDF.',
            'video_url.required' => 'Please enter a video URL.',
            'video_url.url' => 'Please enter a valid URL.',
            'video_url.regex' => 'The URL must be a YouTube or Vimeo link.',
            'title.required' => 'The resource title is required.',
            'resource_folder_id.required' => 'Please select a folder.',
            'resource_folder_id.exists' => 'The selected folder does not exist.',
        ];
    }
}
