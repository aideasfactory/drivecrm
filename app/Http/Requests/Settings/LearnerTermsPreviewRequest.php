<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LearnerTermsPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOwner() === true && ! $this->user()->isRestrictedOwner();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
