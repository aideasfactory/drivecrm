<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'greeting' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'salutation' => ['nullable', 'string', 'max:500'],
            'action_text' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'A subject line is required.',
            'subject.max' => 'The subject line cannot exceed 255 characters.',
            'body.required' => 'Email body copy is required.',
            'body.max' => 'The email body cannot exceed 20,000 characters.',
            'action_text.max' => 'The button label cannot exceed 80 characters.',
        ];
    }
}
