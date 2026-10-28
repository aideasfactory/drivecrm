<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true;
    }

    /**
     * Copy fields are optional: any left out use the saved copy.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'greeting' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'salutation' => ['sometimes', 'nullable', 'string', 'max:500'],
            'action_text' => ['sometimes', 'nullable', 'string', 'max:80'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*' => ['string', 'max:100'],
        ];
    }
}
